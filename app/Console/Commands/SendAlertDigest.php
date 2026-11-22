<?php

namespace App\Console\Commands;

use App\Services\AlertDispatcher;
use Illuminate\Console\Command;

class SendAlertDigest extends Command
{
    protected $signature = 'surveillance:send-digest';

    protected $description = 'Send the daily alert digest to everyone with queued lower-severity alerts';

    public function handle(AlertDispatcher $dispatcher): int
    {
        $this->info('Digests sent: '.$dispatcher->sendDigests());

        return self::SUCCESS;
    }
}
