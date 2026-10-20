<?php

namespace App\Sejours\Services;

use App\Noyau\Messagerie\Messagerie;
use App\Noyau\Models\Utilisateur;
use App\Noyau\Services\Audit;
use App\Sejours\Enums\StatutReservation;
use App\Sejours\Exceptions\ReservationImpossible;
use App\Sejours\Models\Litige;
use App\Sejours\Models\Reservation;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Litiges : le client ou le gérant en ouvre un, l'administrateur le traite.
 * Ouvrir un litige sur une réservation confirmée ou en cours la place « En litige » (plus de check-in tant que c'est ouvert) ;
 * à la clôture, elle retrouve son statut d'avant. Aucun mouvement d'argent n'est déclenché par un litige.
 */
class LitigeService
{
    public function ouvrir(Reservation $reservation, Utilisateur $auteur, string $motif): Litige
    {
        Gate::forUser($auteur)->authorize('ouvrirLitige', $reservation);
        $motif = trim($motif);
        if (mb_strlen($motif) < 10) {
            throw new ReservationImpossible('Expliquez le problème en quelques mots (10 caractères au minimum).');
        }
        if (mb_strlen($motif) > 2000) {
            throw new ReservationImpossible('Votre texte est trop long (2 000 caractères au maximum).');
        }

        $litige = null;
        try {
            DB::transaction(function () use ($reservation, $auteur, $motif, &$litige) {
                $r = Reservation::whereKey($reservation->id)->lockForUpdate()->first();
                if (! in_array($r->statut, [StatutReservation::Confirmee, StatutReservation::ArriveeEnregistree, StatutReservation::Terminee, StatutReservation::Annulee, StatutReservation::Litige], true) || $r->origine === 'hors_plateforme') {
                    throw new ReservationImpossible('On ne peut signaler un problème que sur une réservation confirmée, en cours, terminée ou annulée.');
                }
                $r->loadMissing('unite.residence');
                $gerant = $r->unite->residence->gerant_id;
                $adverse = $auteur->id === $r->client_id ? $gerant : $r->client_id;

                $avant = $r->statut->value;
                if ($r->statut === StatutReservation::Litige) {
                    $avant = Litige::where('reservation_id', $r->id)->where('statut', 'ouvert')->value('statut_avant') ?? StatutReservation::Confirmee->value;
                } elseif (in_array($r->statut, [StatutReservation::Confirmee, StatutReservation::ArriveeEnregistree], true)) {
                    $r->update(['statut' => StatutReservation::Litige]);
                }

                $litige = Litige::create([
                    'reservation_id' => $r->id, 'ouvert_par' => $auteur->id, 'partie_adverse_id' => $adverse,
                    'motif' => $motif, 'statut_avant' => $avant,
                ]);
                Audit::enregistrer('litige.ouvert', $litige, ['reservation' => $r->id], $auteur);
            });
        } catch (UniqueConstraintViolationException) {
            throw new ReservationImpossible('Vous avez déjà signalé un problème pour cette réservation : il est en cours de traitement.');
        }

        $this->prevenir('litige_ouvert', $litige, ['motif' => $motif]);

        return $litige;
    }

    public function clore(Litige $litige, Utilisateur $admin, string $decision): Litige
    {
        Gate::forUser($admin)->authorize('traiter-litiges');
        $decision = trim($decision);
        if (mb_strlen($decision) < 5) {
            throw new ReservationImpossible('Écrivez la décision avant de clore.');
        }

        DB::transaction(function () use ($litige, $admin, $decision) {
            $pris = Litige::whereKey($litige->id)->where('statut', 'ouvert')->update([
                'statut' => 'resolu', 'decision' => mb_substr($decision, 0, 2000), 'decide_par' => $admin->id, 'decide_le' => now(), 'updated_at' => now(),
            ]);
            if ($pris !== 1) {
                throw new ReservationImpossible('Ce litige est déjà clos.');
            }
            $r = Reservation::whereKey($litige->reservation_id)->lockForUpdate()->first();
            $autreOuvert = Litige::where('reservation_id', $r->id)->where('statut', 'ouvert')->exists();
            if ($r->statut === StatutReservation::Litige && ! $autreOuvert) {
                $retour = StatutReservation::tryFrom((string) $litige->statut_avant) ?? StatutReservation::Confirmee;
                $r->update(['statut' => $retour]);
            }
            Audit::enregistrer('litige.clos', $litige, ['reservation' => $r->id], $admin);
        });

        $litige = $litige->fresh();
        $this->prevenir('litige_clos', $litige, ['decision' => $decision]);

        return $litige;
    }

    /** @param array<string, string> $valeurs */
    private function prevenir(string $modele, Litige $litige, array $valeurs): void
    {
        $litige->loadMissing('reservation.unite.residence', 'auteur', 'partieAdverse');
        $valeurs += Messagerie::valeursReservation($litige->reservation);
        Messagerie::envoyer($modele, $litige->auteur, $valeurs, $litige->reservation, (string) $litige->id);
        Messagerie::envoyer($modele, $litige->partieAdverse, $valeurs, $litige->reservation, (string) $litige->id);
    }
}
