<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Collection;

class AlertDigestMail extends Mailable
{
    public function __construct(public User $user, public Collection $alerts) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Daily alert summary: '.$this->alerts->count().' open alert'.($this->alerts->count() === 1 ? '' : 's'));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.digest');
    }
}
