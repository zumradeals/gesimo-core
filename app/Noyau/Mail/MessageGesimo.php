<?php

namespace App\Noyau\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** E-mail générique : le sujet et le texte viennent des modèles de messages (Paramètres). */
class MessageGesimo extends Mailable
{
    /** @param array{texte: string, lien: string}|null $bouton */
    public function __construct(public string $sujet, public string $texte, public ?array $bouton = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->sujet);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.message');
    }
}
