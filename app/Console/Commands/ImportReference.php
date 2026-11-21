<?php

namespace App\Console\Commands;

use App\Models\Antigen;
use App\Models\DataSource;
use App\Models\Disease;
use App\Models\ExternalMetric;
use App\Models\ImportBatch;
use App\Models\ReferenceEstimate;
use Illuminate\Console\Command;

class ImportReference extends Command
{
    protected $signature = 'surveillance:import-reference';

    protected $description = 'Import the WHO immunization estimates and Wikipedia page-view snapshots';

    public function handle(): int
    {
        $dir = database_path('data/external');
        $antigens = Antigen::pluck('id', 'code');
        $diseases = Disease::pluck('id', 'code');

        $who = DataSource::where('code', 'who-gho')->first();
        $batch = ImportBatch::begin('surveillance:import-reference (WHO)', $who);
        $n = 0;
        foreach ($this->csv("$dir/who_immunization_uga.csv") as $r) {
            if (! isset($antigens[$r['antigen']])) {
                continue;
            }
            ReferenceEstimate::updateOrCreate(
                ['antigen_id' => $antigens[$r['antigen']], 'year' => (int) $r['year']],
                ['coverage_percent' => $r['coverage_percent'], 'data_source_id' => $who?->id]
            );
            $n++;
        }
        $batch->finish($n);
        $this->info("WHO estimates: $n");

        $wiki = DataSource::where('code', 'wikimedia-pageviews')->first();
        $batch = ImportBatch::begin('surveillance:import-reference (pageviews)', $wiki);
        $rows = [];
        foreach ($this->csv("$dir/wikipedia_pageviews_weekly.csv") as $r) {
            if (isset($diseases[$r['topic']])) {
                $rows[] = ['disease_id' => $diseases[$r['topic']], 'source' => 'wikipedia', 'metric' => 'pageviews', 'period_start' => $r['week_start'],
                    'value' => (int) $r['views'], 'geography_scope' => 'global', 'import_batch_id' => $batch->id];
            }
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            ExternalMetric::upsert($chunk, ['disease_id', 'source', 'metric', 'period_start'], ['value', 'import_batch_id']);
        }
        $batch->finish(count($rows));
        $this->info('Page-view rows: '.count($rows));

        return self::SUCCESS;
    }

    private function csv(string $path): \Generator
    {
        if (! is_file($path)) {
            return;
        }
        $fh = fopen($path, 'r');
        $head = fgetcsv($fh);
        while (($r = fgetcsv($fh)) !== false) {
            yield array_combine($head, $r);
        }
        fclose($fh);
    }
}
