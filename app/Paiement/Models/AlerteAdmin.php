<?php

namespace App\Paiement\Models;

use Illuminate\Database\Eloquent\Model;

class AlerteAdmin extends Model
{
    protected $table = 'alerte_admin';

    protected $fillable = ['type', 'reservation_id', 'paiement_id', 'remboursement_id', 'message', 'traitee_le', 'traitee_par'];

    protected function casts(): array
    {
        return ['traitee_le' => 'datetime'];
    }

    public static function libelleType(string $type): string
    {
        return match ($type) {
            'paiement_tardif_reattribue' => 'Paiement tardif : réservation confirmée',
            'paiement_tardif_rembourse' => 'Paiement tardif : remboursement',
            'double_paiement' => 'Double paiement',
            'paiement_apres_annulation' => 'Paiement après annulation',
            'montant_different' => 'Montant différent',
            'paiement_inconnu' => 'Paiement inconnu',
            'remboursement_en_echec' => 'Remboursement en échec',
            default => $type,
        };
    }
}
