<?php

namespace App\Mail;

use App\Models\Alert;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

/** Immediate alert email. Contains counts and areas only, never case-level detail. */
class AlertMail extends Mailable
{
    public function __construct(public Alert $alert, public User $user, public bool $escalated = false) {}

    public function envelope(): Envelope
    {
        $prefix = $this->escalated ? 'ESCALATED: ' : '';

        return new Envelope(subject: $prefix.ucfirst($this->alert->severity).' alert: '.$this->alert->disease->name.' in '.$this->alert->orgUnit->name);
    }

    /** Marks the message as automatic and gives mail clients a one-click way to change how alerts arrive. */
    public function headers(): Headers
    {
        return new Headers(text: ['Auto-Submitted' => 'auto-generated', 'List-Unsubscribe' => '<'.route('alerts.index').'>']);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.alert');
    }
}
