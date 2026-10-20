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
        'soumise_le', 'badge_verifie', 'motif_refus', 'verifiee_par', 'verifiee_le',
    ];

    /** Mêmes valeurs par défaut que la base : un modèle neuf est complet sans être relu. */
    protected $attributes = [
        'statut_verification' => 'en_attente',
        'mode_paiement' => 'total',
        'politique_annulation' => 'flexible',
        'badge_verifie' => false,
    ];

    protected function casts(): array
    {
        return [
            'badge_verifie' => 'boolean',
            'soumise_le' => 'datetime',
            'verifiee_le' => 'datetime',
            'pourcentage_acompte' => 'integer',
        ];
    }

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

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class)->orderBy('ordre');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(DocumentVerification::class);
    }

    public function estVerifiee(): bool
    {
        return $this->statut_verification === 'verifiee';
    }
}
