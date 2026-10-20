<?php

namespace App\Noyau\Messagerie;

use App\Noyau\Models\Notification;
use App\Noyau\Models\Utilisateur;
use App\Noyau\Services\Format;
use App\Sejours\Models\Reservation;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Couche « canaux » : écrit le message en base (journal des envois) puis le confie au canal choisi.
 * Un même message (modèle + réservation + destinataire) n'est jamais envoyé deux fois.
 */
class Messagerie
{
    public static function canal(string $nom): Canal
    {
        return match ($nom) {
            'email' => new CanalEmail,
            default => throw new \InvalidArgumentException("Canal inconnu : {$nom}"),
        };
    }

    /** @param array<string, scalar|null> $valeurs */
    public static function envoyer(string $code, ?Utilisateur $destinataire, array $valeurs = [], ?Reservation $reservation = null, ?string $instance = null): ?Notification
    {
        if ($destinataire === null || ! $destinataire->estActif()) {
            return null;
        }
        $modele = ModelesMessages::obtenir($code);
        $valeurs = ['nom' => $destinataire->nom] + $valeurs;

        try {
            $notification = DB::transaction(fn () => Notification::create([
                'destinataire_id' => $destinataire->id,
                'canal' => 'email',
                'sujet' => mb_substr(ModelesMessages::remplir($modele['sujet'], $valeurs), 0, 190),
                'contenu' => ModelesMessages::remplir($modele['corps'], $valeurs),
                'modele' => $instance === null ? $code : $code.'#'.$instance,
                'reservation_id' => $reservation?->id,
            ]));
        } catch (UniqueConstraintViolationException) {
            return null; // déjà envoyé (webhook rejoué, tâche relancée)
        } catch (\Throwable $e) {
            // Un message qui ne part pas ne doit jamais bloquer un paiement ou une annulation.
            report($e);

            return null;
        }

        EnvoyerNotification::dispatch($notification->id)->afterCommit();

        return $notification;
    }

    /** Les valeurs habituelles d'une réservation, pour les modèles. @return array<string, string> */
    public static function valeursReservation(Reservation $reservation, ?int $montant = null): array
    {
        $reservation->loadMissing('unite.residence');

        return [
            'logement' => $reservation->unite->nom,
            'residence' => $reservation->unite->residence->nom,
            'dates' => $reservation->date_arrivee->translatedFormat('j M Y').' → '.$reservation->date_depart->translatedFormat('j M Y'),
            'montant' => Format::fcfa($montant ?? $reservation->montant_total),
            'numero' => (string) $reservation->id,
        ];
    }
}
