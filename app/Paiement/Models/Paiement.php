<?php

namespace App\Paiement\Models;

use App\Sejours\Models\Reservation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Paiement extends Model
{
    protected $table = 'paiement';

    protected $fillable = [
        'reservation_id', 'mise_en_avant_id', 'reference_geniuspay', 'reference_externe', 'montant', 'frais', 'moyen', 'statut',
        'mode', 'fournisseur', 'checkout_url', 'lien_expire_le', 'donnees_brutes',
    ];

    protected $attributes = ['statut' => 'en_attente', 'frais' => 0, 'mode' => 'sandbox', 'fournisseur' => 'geniuspay'];

    protected function casts(): array
    {
        return [
            'montant' => 'integer',
            'frais' => 'integer',
            'donnees_brutes' => 'array',
            'lien_expire_le' => 'datetime',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function remboursement(): HasOne
    {
        return $this->hasOne(Remboursement::class);
    }

    public function estReussi(): bool
    {
        return $this->statut === 'reussi';
    }

    /** Numéro de reçu : stable et sans signification cachée. */
    public function numeroRecu(): string
    {
        return 'R-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }
}
