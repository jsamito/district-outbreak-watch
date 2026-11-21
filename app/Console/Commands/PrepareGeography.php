<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use ZipArchive;

class PrepareGeography extends Command
{
    protected $signature = 'surveillance:prepare-geography
        {from : Directory holding the HDX downloads (uga_admin_boundaries.geojson.zip, uga_admpop_adm2_2023.csv, uganda-healthsites-csv)}
        {--tolerance=0.006 : Polygon simplification tolerance in degrees}';

    protected $description = 'Build compact, committed geography files (districts, population, facilities) from the raw HDX downloads';

    private const TYPE_PRIORITY = ['hospital' => 1, 'hc4' => 2, 'hc3' => 3, 'hc2' => 4, 'clinic' => 5, 'other' => 6];

    public function handle(): int
    {
        ini_set('memory_limit', '2G');
        $from = rtrim($this->argument('from'), '/');
        $out = database_path('data/uganda');
        @mkdir($out, 0775, true);

        foreach (['uga_admin_boundaries.geojson.zip', 'uga_admpop_adm2_2023.csv', 'uganda-healthsites-csv'] as $f) {
            if (! is_file("$from/$f")) {
                $this->error("Missing $from/$f");

                return self::FAILURE;
            }
        }

        $tmp = sys_get_temp_dir().'/ow-geo-'.bin2hex(random_bytes(4));
        mkdir($tmp);
        $zip = new ZipArchive;
        $zip->open("$from/uga_admin_boundaries.geojson.zip");
        $zip->extractTo($tmp, ['uga_admin1.geojson', 'uga_admin2.geojson']);
        $zip->close();

        $regions = [];
        foreach (json_decode(file_get_contents("$tmp/uga_admin1.geojson"), true)['features'] as $f) {
            $regions[$f['properties']['adm1_pcode']] = $f['properties']['adm1_name'];
        }
        ksort($regions);

        $districts = [];
        $features = [];
        foreach (json_decode(file_get_contents("$tmp/uga_admin2.geojson"), true)['features'] as $f) {
            $p = $f['properties'];
            $polys = $f['geometry']['type'] === 'Polygon' ? [$f['geometry']['coordinates']] : $f['geometry']['coordinates'];
            $districts[$p['adm2_pcode']] = ['name' => $p['adm2_name'], 'region' => $p['adm1_pcode'], 'polys' => $polys, 'bbox' => $this->bbox($polys)];
            $tol = (float) $this->option('tolerance');
            $simple = array_map(fn ($poly) => array_map(fn ($ring) => $this->simplify($ring, $tol), $poly), $polys);
            $features[] = [
                'type' => 'Feature',
                'properties' => ['code' => $p['adm2_pcode'], 'name' => $p['adm2_name']],
                'geometry' => count($simple) === 1 ? ['type' => 'Polygon', 'coordinates' => $simple[0]] : ['type' => 'MultiPolygon', 'coordinates' => $simple],
            ];
        }
        ksort($districts);
        usort($features, fn ($a, $b) => strcmp($a['properties']['code'], $b['properties']['code']));
        file_put_contents("$out/districts.geojson", json_encode(['type' => 'FeatureCollection', 'features' => $features], JSON_UNESCAPED_SLASHES));
        $this->info('Districts: '.count($districts).' | map file '.number_format(filesize("$out/districts.geojson") / 1024).' KB');

        // org_units.csv: country, regions, districts
        $fh = fopen("$out/org_units.csv", 'w');
        fputcsv($fh, ['code', 'parent_code', 'type', 'name']);
        fputcsv($fh, ['UG', '', 'country', 'Uganda']);
        foreach ($regions as $code => $name) {
            fputcsv($fh, [$code, 'UG', 'region', $name]);
        }
        foreach ($districts as $code => $d) {
            fputcsv($fh, [$code, $d['region'], 'district', $d['name']]);
        }
        fclose($fh);

        // population.csv
        $under1Share = (float) config('surveillance.under1_share_of_under5');
        $rows = array_map('str_getcsv', file("$from/uga_admpop_adm2_2023.csv", FILE_IGNORE_NEW_LINES));
        $head = array_map(fn ($h) => ltrim($h, "\xEF\xBB\xBF"), array_shift($rows));
        $fh = fopen("$out/population.csv", 'w');
        fputcsv($fh, ['district_code', 'year', 'total', 'under1', 'under5', 'women_15_49']);
        $total = 0;
        foreach ($rows as $r) {
            $r = array_combine($head, $r);
            $women = 0;
            foreach (['15_19', '20_24', '25_29', '30_34', '35_39', '40_44', '45_49'] as $g) {
                $women += (int) $r["F_$g"];
            }
            $u5 = (int) $r['T_00_04'];
            fputcsv($fh, [$r['ADM2_PCODE'], $r['year'], (int) $r['T_TL'], (int) round($u5 * $under1Share), $u5, $women]);
            $total += (int) $r['T_TL'];
        }
        fclose($fh);
        $this->info('Population total: '.number_format($total));

        // facilities.csv
        $fac = $this->facilities("$from/uganda-healthsites-csv", $districts);
        $fh = fopen("$out/facilities.csv", 'w');
        fputcsv($fh, ['code', 'name', 'district_code', 'lat', 'lng', 'facility_type', 'ownership', 'osm_id', 'sentinel']);
        foreach ($fac as $f) {
            fputcsv($fh, [$f['code'], $f['name'], $f['district'], $f['lat'], $f['lng'], $f['type'], $f['ownership'], $f['osm_id'], $f['sentinel'] ? 1 : 0]);
        }
        fclose($fh);
        $sentinel = count(array_filter($fac, fn ($f) => $f['sentinel']));
        $this->info('Facilities: '.count($fac)." (sentinel: $sentinel)");

        file_put_contents("$out/prepare_summary.json", json_encode([
            'prepared_on' => now()->toDateString(), 'districts' => count($districts), 'regions' => count($regions),
            'population_total' => $total, 'facilities' => count($fac), 'sentinel_facilities' => $sentinel,
            'tolerance_degrees' => (float) $this->option('tolerance'),
        ], JSON_PRETTY_PRINT));

        array_map('unlink', glob("$tmp/*"));
        rmdir($tmp);

        return self::SUCCESS;
    }

    private function facilities(string $csv, array $districts): array
    {
        $fh = fopen($csv, 'r');
        $head = fgetcsv($fh);
        $seen = [];
        $list = [];
        while (($r = fgetcsv($fh)) !== false) {
            $r = array_combine($head, $r);
            $amenity = $r['amenity'];
            $health = $r['healthcare'];
            if (! in_array($amenity, ['hospital', 'clinic'], true) && ! in_array($health, ['hospital', 'clinic'], true)) {
                continue;
            }
            $name = trim(preg_replace('/\s+/', ' ', $r['name']));
            if ($name === '' || ! is_numeric($r['X']) || ! is_numeric($r['Y'])) {
                continue;
            }
            $lng = (float) $r['X'];
            $lat = (float) $r['Y'];
            $key = mb_strtoupper($name).'|'.round($lat, 3).'|'.round($lng, 3);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $district = $this->locate($lng, $lat, $districts);
            if ($district === null) {
                continue;
            }
            $list[] = [
                'code' => 'F'.$r['osm_id'],
                'name' => mb_convert_case(mb_strtolower($name), MB_CASE_TITLE),
                'district' => $district,
                'lat' => round($lat, 6),
                'lng' => round($lng, 6),
                'type' => $this->facilityType($name),
                'ownership' => $this->ownership($r['operator_type']),
                'osm_id' => $r['osm_id'],
                'sentinel' => false,
            ];
        }
        fclose($fh);

        // Sentinel selection: up to N per district, highest level first, then name.
        $perDistrict = [];
        foreach ($list as $i => $f) {
            $perDistrict[$f['district']][] = $i;
        }
        $limit = (int) config('surveillance.sentinel_per_district');
        foreach ($perDistrict as $idxs) {
            usort($idxs, fn ($a, $b) => [self::TYPE_PRIORITY[$list[$a]['type']], $list[$a]['name']] <=> [self::TYPE_PRIORITY[$list[$b]['type']], $list[$b]['name']]);
            foreach (array_slice($idxs, 0, $limit) as $i) {
                $list[$i]['sentinel'] = true;
            }
        }
        usort($list, fn ($a, $b) => [$a['district'], $a['name']] <=> [$b['district'], $b['name']]);

        return $list;
    }

    private function facilityType(string $name): string
    {
        $n = mb_strtoupper($name);
        if (preg_match('/HOSPITAL/', $n)) {
            return 'hospital';
        }
        if (preg_match('/(?:H\.?\s?C\.?|HEALTH\s+CENT(?:RE|ER))\s*-?\s*(IV|III|II|4|3|2)\b/', $n, $m)) {
            return match ($m[1]) {
                'IV', '4' => 'hc4',
                'III', '3' => 'hc3',
                default => 'hc2',
            };
        }
        if (preg_match('/HEALTH\s+CENT(?:RE|ER)/', $n)) {
            return 'hc3';
        }
        if (preg_match('/CLINIC|MEDICAL\s+CENT|DISPENSARY/', $n)) {
            return 'clinic';
        }

        return 'other';
    }

    private function ownership(string $operatorType): string
    {
        $o = mb_strtolower($operatorType);

        return match (true) {
            str_contains($o, 'gov'), str_contains($o, 'public') => 'public',
            str_contains($o, 'ngo'), str_contains($o, 'non_profit') => 'ngo',
            str_contains($o, 'private') => 'private',
            default => 'unknown',
        };
    }

    private function bbox(array $polys): array
    {
        $minX = $minY = INF;
        $maxX = $maxY = -INF;
        foreach ($polys as $poly) {
            foreach ($poly[0] as [$x, $y]) {
                $minX = min($minX, $x);
                $maxX = max($maxX, $x);
                $minY = min($minY, $y);
                $maxY = max($maxY, $y);
            }
        }

        return [$minX, $minY, $maxX, $maxY];
    }

    private function locate(float $x, float $y, array $districts): ?string
    {
        foreach ($districts as $code => $d) {
            [$a, $b, $c, $e] = $d['bbox'];
            if ($x < $a || $x > $c || $y < $b || $y > $e) {
                continue;
            }
            foreach ($d['polys'] as $poly) {
                if ($this->inRing($x, $y, $poly[0])) {
                    $inHole = false;
                    for ($h = 1; $h < count($poly); $h++) {
                        if ($this->inRing($x, $y, $poly[$h])) {
                            $inHole = true;
                            break;
                        }
                    }
                    if (! $inHole) {
                        return $code;
                    }
                }
            }
        }

        return null;
    }

    private function inRing(float $x, float $y, array $ring): bool
    {
        $inside = false;
        $n = count($ring);
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            [$xi, $yi] = $ring[$i];
            [$xj, $yj] = $ring[$j];
            if ((($yi > $y) !== ($yj > $y)) && ($x < ($xj - $xi) * ($y - $yi) / (($yj - $yi) ?: 1e-12) + $xi)) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    /** Iterative Ramer-Douglas-Peucker simplification of a closed ring. */
    private function simplify(array $ring, float $eps): array
    {
        $n = count($ring);
        if ($n < 8) {
            return array_map(fn ($p) => [round($p[0], 4), round($p[1], 4)], $ring);
        }
        $keep = array_fill(0, $n, false);
        $keep[0] = $keep[$n - 1] = true;
        $stack = [[0, $n - 1]];
        while ($stack) {
            [$s, $e] = array_pop($stack);
            [$x1, $y1] = $ring[$s];
            [$x2, $y2] = $ring[$e];
            $dx = $x2 - $x1;
            $dy = $y2 - $y1;
            $norm = sqrt($dx * $dx + $dy * $dy);
            $best = 0.0;
            $idx = -1;
            for ($i = $s + 1; $i < $e; $i++) {
                [$x0, $y0] = $ring[$i];
                $d = $norm > 0 ? abs($dy * $x0 - $dx * $y0 + $x2 * $y1 - $y2 * $x1) / $norm : sqrt(($x0 - $x1) ** 2 + ($y0 - $y1) ** 2);
                if ($d > $best) {
                    $best = $d;
                    $idx = $i;
                }
            }
            if ($best > $eps && $idx > 0) {
                $keep[$idx] = true;
                $stack[] = [$s, $idx];
                $stack[] = [$idx, $e];
            }
        }
        $out = [];
        foreach ($ring as $i => $p) {
            if ($keep[$i]) {
                $out[] = [round($p[0], 4), round($p[1], 4)];
            }
        }

        return count($out) >= 4 ? $out : array_map(fn ($p) => [round($p[0], 4), round($p[1], 4)], $ring);
    }
}
