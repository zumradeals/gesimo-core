<?php

namespace App\Paiement\Models;

use App\Sejours\Models\Reservation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Remboursement extends Model
{
    protected $table = 'remboursement';

    protected $fillable = [
        'reservation_id', 'paiement_id', 'montant', 'motif', 'statut', 'effectue_le',
        'reference_fournisseur', 'tentatives', 'derniere_erreur',
    ];

    protected $attributes = ['statut' => 'en_attente', 'tentatives' => 0];

    protected function casts(): array
    {
        return ['montant' => 'integer', 'effectue_le' => 'datetime'];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function paiement(): BelongsTo
    {
        return $this->belongsTo(Paiement::class);
    }
}
