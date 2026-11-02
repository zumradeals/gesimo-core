<?php

namespace App\Noyau\Messagerie;

use App\Noyau\Mail\MessageGesimo;
use App\Noyau\Models\Notification;
use App\Noyau\Services\Courriel;
use App\Sejours\Models\Reservation;
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
        Mail::to($adresse)->send(new MessageGesimo($notification->sujet, $notification->contenu, self::bouton($notification)));
    }

    /** Un grand bouton pour les messages au voyageur qui ont une suite évidente (voir sa réservation, ajouter sa pièce). */
    private static function bouton(Notification $notification): ?array
    {
        $code = explode('#', (string) $notification->modele)[0];
        $reservation = $notification->reservation_id ? Reservation::find($notification->reservation_id) : null;
        if ($reservation === null || $reservation->client_id !== $notification->destinataire_id) {
            return null;
        }

        return match ($code) {
            'confirmation', 'rappel_arrivee' => ['texte' => 'Voir ma réservation', 'lien' => route('reservation.detail', $reservation)],
            'rappel_piece' => ['texte' => 'Ajouter ma pièce d\'identité', 'lien' => route('reservation.detail', $reservation).'#piece-identite'],
            default => null,
        };
    }
}
