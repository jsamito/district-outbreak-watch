<?php

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class FetchReference extends Command
{
    protected $signature = 'surveillance:fetch-reference {--weeks=112 : Weeks of page-view history}';

    protected $description = 'Download dated snapshots of WHO GHO immunization estimates (Uganda) and Wikipedia page views into database/data/external';

    private const UA = 'DistrictOutbreakWatch/1.0 (health surveillance dashboard; periodic data snapshot)';

    private const ARTICLES = ['malaria' => 'Malaria', 'measles' => 'Measles', 'cholera' => 'Cholera', 'typhoid' => 'Typhoid_fever', 'ebola' => 'Ebola_virus_disease'];

    public function handle(): int
    {
        $out = database_path('data/external');
        @mkdir($out, 0775, true);

        // WHO GHO: find the polio indicator code by name, then fetch the four antigens for Uganda.
        $gho = 'https://ghoapi.azureedge.net/api';
        $codes = ['BCG' => 'WHS4_543', 'PENTA3' => 'WHS4_100', 'MCV1' => 'WHS8_110'];
        $polio = Http::withUserAgent(self::UA)->timeout(60)->get("$gho/Indicator", ['$filter' => "contains(IndicatorName,'Polio (Pol3) immunization coverage among 1-year-olds')"])->json('value', []);
        if ($polio) {
            $codes['OPV3'] = $polio[0]['IndicatorCode'];
        }
        $rows = [];
        foreach ($codes as $antigen => $code) {
            $res = Http::withUserAgent(self::UA)->timeout(60)->retry(3, 1500)->get("$gho/$code", ['$filter' => "SpatialDim eq 'UGA'"]);
            foreach ($res->json('value', []) as $r) {
                if ($r['NumericValue'] !== null) {
                    $rows[] = [$antigen, $code, (int) $r['TimeDim'], $r['NumericValue']];
                }
            }
        }
        usort($rows, fn ($a, $b) => [$a[0], $a[2]] <=> [$b[0], $b[2]]);
        $fh = fopen("$out/who_immunization_uga.csv", 'w');
        fputcsv($fh, ['antigen', 'gho_code', 'year', 'coverage_percent']);
        foreach ($rows as $r) {
            fputcsv($fh, $r);
        }
        fclose($fh);
        $this->info('WHO GHO rows: '.count($rows));

        // Wikipedia page views per ISO week.
        $end = CarbonImmutable::yesterday();
        $start = $end->subWeeks((int) $this->option('weeks'));
        $weekly = [];
        foreach (self::ARTICLES as $key => $article) {
            $url = "https://wikimedia.org/api/rest_v1/metrics/pageviews/per-article/en.wikipedia/all-access/user/$article/daily/{$start->format('Ymd')}/{$end->format('Ymd')}";
            $items = Http::withUserAgent(self::UA)->timeout(60)->retry(3, 2000)->get($url)->json('items', []);
            foreach ($items as $it) {
                $day = CarbonImmutable::createFromFormat('Ymd', substr($it['timestamp'], 0, 8));
                $monday = $day->startOfWeek()->toDateString();
                $weekly[$key][$monday] = ($weekly[$key][$monday] ?? 0) + $it['views'];
            }
            usleep(400000);
        }
        $fh = fopen("$out/wikipedia_pageviews_weekly.csv", 'w');
        fputcsv($fh, ['topic', 'week_start', 'views']);
        $n = 0;
        foreach ($weekly as $key => $weeks) {
            ksort($weeks);
            foreach ($weeks as $w => $v) {
                fputcsv($fh, [$key, $w, $v]);
                $n++;
            }
        }
        fclose($fh);
        file_put_contents("$out/fetch_summary.json", json_encode(['fetched_on' => now()->toDateString(), 'who_rows' => count($rows), 'pageview_rows' => $n, 'indicators' => $codes], JSON_PRETTY_PRINT));
        $this->info("Page-view rows: $n");

        return self::SUCCESS;
    }
}
