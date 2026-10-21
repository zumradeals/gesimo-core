<?php

namespace App\Paiement\Models;

use App\Noyau\Models\Utilisateur;
use App\Sejours\Models\Residence;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MiseEnAvant extends Model
{
    protected $table = 'mise_en_avant';

    protected $fillable = ['residence_id', 'date_debut', 'date_fin', 'prix', 'statut', 'offre', 'jours', 'cree_par', 'suspendue_le', 'suspendue_par', 'suspension_motif'];

    protected $attributes = ['statut' => 'en_attente_paiement'];

    protected function casts(): array
    {
        return ['date_debut' => 'date', 'date_fin' => 'date', 'prix' => 'integer', 'jours' => 'integer', 'suspendue_le' => 'datetime'];
    }

    public function residence(): BelongsTo
    {
        return $this->belongsTo(Residence::class);
    }

    public function paiements(): HasMany
    {
        return $this->hasMany(Paiement::class);
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'cree_par');
    }

    /** Annonces réellement mises en avant aujourd'hui : payées, non suspendues, dans leur période. */
    public function scopeEnCours(Builder $q): Builder
    {
        $jour = now()->toDateString();

        return $q->where('statut', 'active')->whereDate('date_debut', '<=', $jour)->whereDate('date_fin', '>=', $jour);
    }

    /** Libellé affiché au gérant. */
    public function etat(): string
    {
        $jour = now()->startOfDay();

        return match (true) {
            $this->statut === 'suspendue' => 'Suspendue par Avelis',
            $this->statut === 'terminee' => 'Terminée',
            $this->statut === 'annulee' => 'Annulée',
            $this->statut === 'en_attente_paiement' => 'En attente de paiement',
            $this->date_fin->lt($jour) => 'Terminée',
            $this->date_debut->gt($jour) => 'Programmée',
            default => 'En cours',
        };
    }
}
