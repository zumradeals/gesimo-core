<?php

namespace App\Sejours\Policies;

use App\Noyau\Enums\Role;
use App\Noyau\Models\Utilisateur;
use App\Sejours\Models\MembreResidence;
use App\Sejours\Models\Reservation;
use App\Sejours\Models\Unite;

class ReservationPolicy
{
    public function view(Utilisateur $utilisateur, Reservation $reservation): bool
    {
        if (! $utilisateur->estActif()) {
            return false;
        }

        return $reservation->client_id === $utilisateur->getKey()
            || $utilisateur->estAdmin()
            || $this->estGerantDe($utilisateur, $reservation)
            || $this->membreAvecDroit($utilisateur, $reservation, 'peut_gerer_calendrier')
            || $this->membreAvecDroit($utilisateur, $reservation, 'peut_gerer_checkin_checkout');
    }

    /** Réserver en ligne : un client connecté et actif. */
    public function create(Utilisateur $utilisateur): bool
    {
        return $utilisateur->estActif() && $utilisateur->aRole(Role::Client);
    }

    /** RG-5 : seul le gérant de la résidence saisit une réservation prise par téléphone. */
    public function creerHorsPlateforme(Utilisateur $utilisateur, Unite $unite): bool
    {
        return $utilisateur->estActif()
            && $utilisateur->aRole(Role::Gerant)
            && $unite->residence->gerant_id === $utilisateur->getKey();
    }

    /** Payer : seulement le client de la réservation. */
    public function payer(Utilisateur $utilisateur, Reservation $reservation): bool
    {
        return $utilisateur->estActif() && $reservation->client_id === $utilisateur->getKey();
    }

    /** Reçu et confirmation : le client concerné, ou l'équipe Avelis. */
    public function voirRecu(Utilisateur $utilisateur, Reservation $reservation): bool
    {
        return $utilisateur->estActif() && ($reservation->client_id === $utilisateur->getKey() || $utilisateur->estAdmin());
    }

    public function annuler(Utilisateur $utilisateur, Reservation $reservation): bool
    {
        return $utilisateur->estActif()
            && ($reservation->client_id === $utilisateur->getKey() || $this->estGerantDe($utilisateur, $reservation) || $utilisateur->estAdmin());
    }

    /** Avis : le client du séjour (la base refuse tant que le séjour n'est pas terminé). */
    public function donnerAvis(Utilisateur $utilisateur, Reservation $reservation): bool
    {
        return $utilisateur->estActif() && $reservation->client_id === $utilisateur->getKey();
    }

    /** Signaler un problème : le client du séjour ou le gérant de la résidence. */
    public function ouvrirLitige(Utilisateur $utilisateur, Reservation $reservation): bool
    {
        return $utilisateur->estActif() && ($reservation->client_id === $utilisateur->getKey() || $this->estGerantDe($utilisateur, $reservation));
    }

    /** Check-in et check-out : gérant, ou personnel autorisé par le gérant. */
    public function enregistrerArrivee(Utilisateur $utilisateur, Reservation $reservation): bool
    {
        return $utilisateur->estActif()
            && ($this->estGerantDe($utilisateur, $reservation) || $this->membreAvecDroit($utilisateur, $reservation, 'peut_gerer_checkin_checkout'));
    }

    private function estGerantDe(Utilisateur $utilisateur, Reservation $reservation): bool
    {
        return $utilisateur->aRole(Role::Gerant) && $reservation->unite->residence->gerant_id === $utilisateur->getKey();
    }

    private function membreAvecDroit(Utilisateur $utilisateur, Reservation $reservation, string $droit): bool
    {
        return $utilisateur->aRole(Role::Personnel)
            && MembreResidence::where('residence_id', $reservation->unite->residence_id)
                ->where('utilisateur_id', $utilisateur->getKey())
                ->where($droit, true)
                ->exists();
    }
}
