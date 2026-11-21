<?php

namespace App\Console\Commands;

use App\Models\DataSource;
use App\Models\ImportBatch;
use App\Models\OrgUnit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportGeography extends Command
{
    protected $signature = 'surveillance:import-geography';

    protected $description = 'Import org units (country, regions, districts, facilities), district polygons and populations from database/data/uganda';

    public function handle(): int
    {
        $dir = database_path('data/uganda');
        foreach (['org_units.csv', 'districts.geojson', 'population.csv', 'facilities.csv'] as $f) {
            if (! is_file("$dir/$f")) {
                $this->error("Missing $dir/$f. Run surveillance:prepare-geography first.");

                return self::FAILURE;
            }
        }

        $batch = ImportBatch::begin('surveillance:import-geography', DataSource::where('code', 'hdx-cod-ab-uga')->first());
        $rows = 0;

        DB::transaction(function () use ($dir, &$rows) {
            $geo = [];
            foreach (json_decode(file_get_contents("$dir/districts.geojson"), true)['features'] as $f) {
                $geo[$f['properties']['code']] = $f['geometry'];
            }

            $byCode = [];
            foreach ($this->csv("$dir/org_units.csv") as $r) {
                $parent = $r['parent_code'] !== '' ? $byCode[$r['parent_code']] : null;
                $level = ['country' => 1, 'region' => 2, 'district' => 3][$r['type']];
                $unit = OrgUnit::updateOrCreate(['code' => $r['code']], [
                    'parent_id' => $parent?->id,
                    'level' => $level,
                    'type' => $r['type'],
                    'name' => $r['name'],
                    'path' => $parent ? $parent->path.'/'.$r['code'] : $r['code'],
                    'geometry' => $geo[$r['code']] ?? null,
                    'active' => true,
                ]);
                $byCode[$r['code']] = $unit;
                $rows++;
            }

            // Facilities
            $now = now();
            $units = [];
            $profiles = [];
            foreach ($this->csv("$dir/facilities.csv") as $r) {
                $district = $byCode[$r['district_code']] ?? null;
                if (! $district) {
                    continue;
                }
                $units[] = [
                    'parent_id' => $district->id, 'level' => 4, 'type' => 'facility', 'name' => $r['name'], 'code' => $r['code'],
                    'path' => $district->path.'/'.$r['code'], 'lat' => $r['lat'], 'lng' => $r['lng'], 'active' => true,
                    'created_at' => $now, 'updated_at' => $now,
                ];
                $profiles[$r['code']] = ['facility_type' => $r['facility_type'], 'ownership' => $r['ownership'] ?: null, 'source_ref' => $r['osm_id'], 'sentinel' => (bool) $r['sentinel']];
            }
            foreach (array_chunk($units, 1000) as $chunk) {
                OrgUnit::upsert($chunk, ['code'], ['parent_id', 'name', 'path', 'lat', 'lng', 'active', 'updated_at']);
            }
            $ids = OrgUnit::where('type', 'facility')->pluck('id', 'code');
            $insert = [];
            foreach ($profiles as $code => $p) {
                if (isset($ids[$code])) {
                    $insert[] = ['org_unit_id' => $ids[$code], 'created_at' => $now, 'updated_at' => $now] + $p;
                }
            }
            foreach (array_chunk($insert, 1000) as $chunk) {
                DB::table('facility_profiles')->upsert($chunk, ['org_unit_id'], ['facility_type', 'ownership', 'source_ref', 'sentinel', 'updated_at']);
            }
            $rows += count($units);

            // Populations: the open dataset has one projected year; other years use flat growth.
            $source = DataSource::where('code', 'hdx-cod-ps-uga')->value('id');
            $base = (int) config('surveillance.population_base_year');
            $growth = (float) config('surveillance.population_growth');
            $pop = [];
            foreach ($this->csv("$dir/population.csv") as $r) {
                $unit = $byCode[$r['district_code']] ?? null;
                if (! $unit) {
                    continue;
                }
                for ($year = $base - 3; $year <= $base + 5; $year++) {
                    $f = (1 + $growth) ** ($year - $base);
                    $pop[] = [
                        'org_unit_id' => $unit->id, 'year' => $year,
                        'total' => (int) round($r['total'] * $f), 'under1' => (int) round($r['under1'] * $f),
                        'under5' => (int) round($r['under5'] * $f), 'women_15_49' => (int) round($r['women_15_49'] * $f),
                        'data_source_id' => $source, 'projected' => $year !== $base,
                    ];
                }
            }
            foreach (array_chunk($pop, 500) as $chunk) {
                DB::table('populations')->upsert($chunk, ['org_unit_id', 'year'], ['total', 'under1', 'under5', 'women_15_49', 'data_source_id', 'projected']);
            }
            $rows += count($pop);
        });

        $batch->finish($rows, 0);
        $this->info("Imported $rows rows: ".OrgUnit::count().' org units ('.OrgUnit::where('type', 'facility')->count().' facilities).');

        return self::SUCCESS;
    }

    /** @return \Generator<array<string,string>> */
    private function csv(string $path): \Generator
    {
        $fh = fopen($path, 'r');
        $head = fgetcsv($fh);
        while (($r = fgetcsv($fh)) !== false) {
            yield array_combine($head, $r);
        }
        fclose($fh);
    }
}
