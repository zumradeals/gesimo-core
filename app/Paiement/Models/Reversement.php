<?php

namespace App\Paiement\Models;

use App\Noyau\Models\Utilisateur;
use App\Sejours\Models\Reservation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reversement extends Model
{
    protected $table = 'reversement';

    protected $fillable = ['gerant_id', 'reservation_id', 'montant', 'statut', 'paye_le', 'paye_par', 'reference_mobile_money'];

    protected $attributes = ['statut' => 'a_payer'];

    protected function casts(): array
    {
        return ['montant' => 'integer', 'paye_le' => 'datetime'];
    }

    public function gerant(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'gerant_id');
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function estPaye(): bool
    {
        return $this->statut === 'paye';
    }
}
