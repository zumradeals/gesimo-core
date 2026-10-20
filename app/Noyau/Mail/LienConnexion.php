<?php

namespace App\Noyau\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LienConnexion extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public string $nom, public string $lien, public int $minutes) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Votre lien de connexion GESIMO');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.lien-connexion');
    }
}
