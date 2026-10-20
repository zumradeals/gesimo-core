<?php

namespace App\Sejours\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvitationPersonnelMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public string $nom, public string $residence, public string $gerant, public string $lien) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Invitation à rejoindre {$this->residence} sur GESIMO");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.invitation-personnel');
    }
}
