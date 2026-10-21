<?php

namespace App\Paiement\Services;

use App\Paiement\Models\AlerteAdmin;

/** Alertes pour l'équipe Avelis : paiements tardifs, doubles paiements, remboursements en échec… */
class Alertes
{
    public static function creer(string $type, string $message, ?int $reservationId = null, ?int $paiementId = null, ?int $remboursementId = null): AlerteAdmin
    {
        // Une seule alerte ouverte par sujet : pas de répétition à chaque nouvel essai.
        $ouverte = AlerteAdmin::whereNull('traitee_le')->where('type', $type)
            ->where('paiement_id', $paiementId)->where('remboursement_id', $remboursementId)->first();
        if ($ouverte !== null) {
            $ouverte->update(['message' => $message]);

            return $ouverte;
        }

        return AlerteAdmin::create([
            'type' => $type, 'message' => $message,
            'reservation_id' => $reservationId, 'paiement_id' => $paiementId, 'remboursement_id' => $remboursementId,
        ]);
    }

    public static function clore(string $type, ?int $paiementId = null, ?int $remboursementId = null, ?int $parQui = null): void
    {
        AlerteAdmin::whereNull('traitee_le')->where('type', $type)
            ->where('paiement_id', $paiementId)->where('remboursement_id', $remboursementId)
            ->update(['traitee_le' => now(), 'traitee_par' => $parQui]);
    }
}
