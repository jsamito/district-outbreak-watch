<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestMail extends Command
{
    protected $signature = 'surveillance:test-mail {to : Address that receives the test message}';

    protected $description = 'Send one test email with the configured mail settings, to check delivery and SPF, DKIM and DMARC results in the message headers';

    public function handle(): int
    {
        $this->line('Mailer: '.config('mail.default').' | From: '.config('mail.from.address'));
        try {
            Mail::raw("This is a test message from ".config('app.name').".\nIf you can read it, outgoing email works. Check the message headers for spf=pass, dkim=pass and dmarc=pass.", function ($m): void {
                $m->to($this->argument('to'))->subject('Test message from '.config('app.name'));
            });
        } catch (\Throwable $e) {
            $this->error('Sending failed: '.$e->getMessage());

            return self::FAILURE;
        }
        $this->info('Handed to the mail server. '.(config('mail.default') === 'log' ? 'The mailer is "log", so the message was written to storage/logs instead of being sent.' : ''));

        return self::SUCCESS;
    }
}
