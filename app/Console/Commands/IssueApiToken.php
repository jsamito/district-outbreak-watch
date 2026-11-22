<?php

namespace App\Console\Commands;

use App\Models\ApiClient;
use App\Models\OrgUnit;
use App\Support\Audit;
use Illuminate\Console\Command;

class IssueApiToken extends Command
{
    protected $signature = 'surveillance:issue-api-token {name : Name of the reporting system} {area : Org unit code the system may report for (a facility or district)}';

    protected $description = 'Register a facility system for the notification API and print its token once';

    public function handle(): int
    {
        $unit = OrgUnit::where('code', $this->argument('area'))->first();
        if (! $unit) {
            $this->error('Unknown area code.');

            return self::FAILURE;
        }
        [$client, $token] = ApiClient::issue($this->argument('name'), $unit);
        Audit::log(Audit::CONFIG, 'api_client_issued', $client, ['area' => $unit->code]);
        $this->info("Registered {$client->name} for {$unit->name}.");
        $this->line("Token (shown once, store it securely): $token");

        return self::SUCCESS;
    }
}
