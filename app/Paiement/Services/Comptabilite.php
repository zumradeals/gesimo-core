<?php

namespace App\Paiement\Services;

use App\Noyau\Models\Utilisateur;
use App\Paiement\Models\EcritureComptable;
use App\Paiement\Models\Paiement;
use App\Paiement\Models\Remboursement;
use App\Paiement\Models\Reversement;
use App\Sejours\Models\Reservation;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Écritures comptables : chaque mouvement d'argent est une écriture inaltérable, enregistrée UNE seule fois
 * (des index uniques dans la base empêchent tout doublon, même si un message arrive deux fois).
 *
 * Le sens des écritures :
 *  - encaissement : argent reçu de GeniusPay (un par paiement réussi) ;
 *  - commission et part_gerant : partage de l'argent « acquis » (à l'arrivée, ou lors d'une annulation tardive) ;
 *  - remboursement : argent rendu au client ;
 *  - reversement : argent versé au gérant (Mobile Money, hors plateforme).
 * Fonds détenus = encaissements − remboursements − commissions − parts du gérant.
 */
class Comptabilite
{
    public static function encaissement(Paiement $paiement, ?Utilisateur $auteur = null): ?EcritureComptable
    {
        return self::ecrire(
            ['paiement_id' => $paiement->id, 'type' => 'encaissement', 'correction_de' => null],
            ['reservation_id' => $paiement->reservation_id, 'montant' => $paiement->montant, 'cree_par' => $auteur?->id],
        );
    }

    public static function commission(Reservation $reservation, int $montant, ?Utilisateur $auteur = null, ?string $motif = null): ?EcritureComptable
    {
        return self::ecrire(
            ['reservation_id' => $reservation->id, 'type' => 'commission', 'correction_de' => null],
            ['montant' => $montant, 'cree_par' => $auteur?->id, 'motif' => $motif],
        );
    }

    public static function partGerant(Reservation $reservation, int $montant, ?Utilisateur $auteur = null, ?string $motif = null): ?EcritureComptable
    {
        return self::ecrire(
            ['reservation_id' => $reservation->id, 'type' => 'part_gerant', 'correction_de' => null],
            ['montant' => $montant, 'cree_par' => $auteur?->id, 'motif' => $motif],
        );
    }

    public static function remboursement(Remboursement $remboursement, ?Utilisateur $auteur = null): ?EcritureComptable
    {
        return self::ecrire(
            ['remboursement_id' => $remboursement->id, 'type' => 'remboursement', 'correction_de' => null],
            ['reservation_id' => $remboursement->reservation_id, 'paiement_id' => $remboursement->paiement_id, 'montant' => $remboursement->montant, 'cree_par' => $auteur?->id, 'motif' => $remboursement->motif],
        );
    }

    public static function reversement(Reversement $reversement, ?Utilisateur $auteur = null): ?EcritureComptable
    {
        return self::ecrire(
            ['reversement_id' => $reversement->id, 'type' => 'reversement', 'correction_de' => null],
            ['reservation_id' => $reversement->reservation_id, 'montant' => $reversement->montant, 'cree_par' => $auteur?->id],
        );
    }

    /**
     * Commission d'une réservation sur une base donnée (prix du séjour, ou prix d'une nuit retenue),
     * arrondie au franc le plus proche, sans jamais dépasser ce qui a été encaissé.
     */
    public static function calculerCommission(Reservation $reservation, int $base, int $plafond): int
    {
        $pourcentage = $reservation->commission_pourcentage !== null
            ? (float) $reservation->commission_pourcentage
            : (ReglagesPaiement::commissionPourcentage() ?? 0.0);

        return max(0, min($plafond, (int) round($base * $pourcentage / 100, 0, PHP_ROUND_HALF_UP)));
    }

    /** Somme réellement encaissée sur une réservation (paiements réussis, avant remboursements). */
    public static function encaisse(Reservation $reservation): int
    {
        return (int) DB::table('paiement')->where('reservation_id', $reservation->id)->where('statut', 'reussi')->sum('montant');
    }

    /**
     * Écrit l'écriture si elle n'existe pas déjà. Renvoie l'écriture créée, ou null si elle existait déjà.
     *
     * @param  array<string, mixed>  $identite
     * @param  array<string, mixed>  $details
     */
    private static function ecrire(array $identite, array $details): ?EcritureComptable
    {
        if ($details['montant'] <= 0) {
            return null; // rien à écrire : les montants sont toujours strictement positifs
        }

        $existe = EcritureComptable::query()->where(array_filter($identite, fn ($v) => $v !== null))
            ->when(array_key_exists('correction_de', $identite), fn ($q) => $q->whereNull('correction_de'))->exists();
        if ($existe) {
            return null;
        }

        try {
            return DB::transaction(fn () => EcritureComptable::create(array_merge(array_filter($identite, fn ($v) => $v !== null), $details)));
        } catch (UniqueConstraintViolationException) {
            return null; // écrite entre-temps par un autre traitement
        }
    }
}
