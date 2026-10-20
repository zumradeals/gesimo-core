<?php

namespace App\Noyau\Messagerie;

use App\Noyau\Mail\MessageGesimo;
use App\Noyau\Models\Notification;
use App\Noyau\Services\Courriel;
use Illuminate\Support\Facades\Mail;

class CanalEmail implements Canal
{
    public function nom(): string
    {
        return 'email';
    }

    public function envoyer(Notification $notification): void
    {
        $adresse = $notification->destinataire?->email;
        if ($adresse === null || $adresse === '') {
            throw new \RuntimeException('Le destinataire n\'a pas d\'adresse e-mail.');
        }
        Courriel::appliquerLesReglages();
        Mail::to($adresse)->send(new MessageGesimo($notification->sujet, $notification->contenu));
    }
}
