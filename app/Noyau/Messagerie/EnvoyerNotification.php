<?php

namespace App\Noyau\Messagerie;

use App\Noyau\Models\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Envoie un message déjà écrit en base, par son canal, et note le résultat. Rejouable sans danger. */
class EnvoyerNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $notificationId) {}

    public function handle(): void
    {
        $notification = Notification::with('destinataire')->find($this->notificationId);
        if ($notification === null || $notification->statut === 'envoye') {
            return;
        }

        $canal = Messagerie::canal($notification->canal);
        try {
            $canal->envoyer($notification);
            $notification->update(['statut' => 'envoye', 'envoye_le' => now(), 'erreur' => null]);
        } catch (\Throwable $e) {
            // Jamais de secret dans l'erreur gardée : on ne garde que le début du message.
            $notification->update(['statut' => 'echoue', 'erreur' => mb_substr($e->getMessage(), 0, 300)]);
        }
    }
}
