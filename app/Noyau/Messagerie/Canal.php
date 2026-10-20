<?php

namespace App\Noyau\Messagerie;

use App\Noyau\Models\Notification;

/** Un moyen d'envoyer un message : e-mail aujourd'hui, SMS et WhatsApp plus tard, sans changer les modèles de messages. */
interface Canal
{
    public function nom(): string;

    /** Envoie le message ; lève une exception si l'envoi échoue. */
    public function envoyer(Notification $notification): void;
}
