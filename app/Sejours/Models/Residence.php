<?php

namespace App\Sejours\Models;

use App\Noyau\Models\Utilisateur;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Residence extends Model
{
    protected $table = 'residence';

    protected $fillable = [
        'gerant_id', 'nom', 'commune', 'quartier', 'latitude', 'longitude', 'reglement',
        'statut_verification', 'mode_paiement', 'pourcentage_acompte', 'politique_annulation',
    ];

    public function gerant(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'gerant_id');
    }

    public function unites(): HasMany
    {
        return $this->hasMany(Unite::class);
    }

    public function membres(): HasMany
    {
        return $this->hasMany(MembreResidence::class);
    }

    public function estVerifiee(): bool
    {
        return $this->statut_verification === 'verifiee';
    }
}
