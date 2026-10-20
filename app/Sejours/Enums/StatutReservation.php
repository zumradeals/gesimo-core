<?php

namespace App\Sejours\Enums;

enum StatutReservation: string
{
    case EnAttente = 'en_attente';
    case Confirmee = 'confirmee';
    case ArriveeEnregistree = 'arrivee_enregistree';
    case Terminee = 'terminee';
    case Annulee = 'annulee';
    case Expiree = 'expiree';
    case Litige = 'litige';
    case HorsPlateforme = 'hors_plateforme';

    public function libelle(): string
    {
        return match ($this) {
            self::EnAttente => 'En attente',
            self::Confirmee => 'Confirmée',
            self::ArriveeEnregistree => 'Arrivée enregistrée',
            self::Terminee => 'Terminée',
            self::Annulee => 'Annulée',
            self::Expiree => 'Expirée',
            self::Litige => 'Litige',
            self::HorsPlateforme => 'Prise par téléphone',
        };
    }

    /** Couleur du badge : attente, succes, info, neutre, danger. */
    public function ton(): string
    {
        return match ($this) {
            self::EnAttente => 'attente',
            self::Confirmee, self::ArriveeEnregistree => 'succes',
            self::HorsPlateforme => 'info',
            self::Terminee, self::Expiree => 'neutre',
            self::Annulee, self::Litige => 'danger',
        };
    }

    /** Statuts qui occupent le calendrier (RG-1). */
    public function occupeLeCalendrier(): bool
    {
        return in_array($this, [self::EnAttente, self::Confirmee, self::ArriveeEnregistree, self::HorsPlateforme], true);
    }

    /**
     * Changements permis. Même liste que le déclencheur PostgreSQL
     * garder_transition_statut_reservation : un test vérifie qu'elles concordent.
     *
     * @return list<self>
     */
    public function transitionsPossibles(bool $horsPlateforme = false): array
    {
        if ($horsPlateforme) {
            return $this === self::HorsPlateforme ? [self::Annulee, self::Terminee] : [];
        }

        return match ($this) {
            self::EnAttente => [self::Confirmee, self::Expiree, self::Annulee],
            // Un paiement arrivé après l'expiration peut redonner les dates si elles sont encore libres (la base le vérifie).
            self::Expiree => [self::Confirmee],
            self::Confirmee => [self::ArriveeEnregistree, self::Annulee, self::Litige],
            self::ArriveeEnregistree => [self::Terminee, self::Litige],
            // À la clôture d'un litige, la réservation retrouve le statut qu'elle avait avant.
            self::Litige => [self::Terminee, self::Annulee, self::Confirmee, self::ArriveeEnregistree],
            default => [],
        };
    }

    public function peutPasserA(self $cible, bool $horsPlateforme = false): bool
    {
        return in_array($cible, $this->transitionsPossibles($horsPlateforme), true);
    }
}
