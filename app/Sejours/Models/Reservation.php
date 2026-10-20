<?php

namespace App\Sejours\Models;

use App\Noyau\Models\Utilisateur;
use App\Paiement\Models\Paiement;
use App\Sejours\Enums\StatutReservation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reservation extends Model
{
    protected $table = 'reservation';

    protected $fillable = [
        'unite_id', 'client_id', 'nom_client', 'telephone_client', 'date_arrivee', 'date_depart',
        'montant_total', 'acompte', 'solde', 'statut', 'origine', 'expire_le', 'cree_par', 'nombre_personnes',
        'commission_pourcentage', 'annulation_motif', 'annulee_le',
    ];

    protected function casts(): array
    {
        return [
            'date_arrivee' => 'date',
            'date_depart' => 'date',
            'expire_le' => 'datetime',
            'annulee_le' => 'datetime',
            'montant_total' => 'integer',
            'acompte' => 'integer',
            'solde' => 'integer',
            'statut' => StatutReservation::class,
        ];
    }

    public function unite(): BelongsTo
    {
        return $this->belongsTo(Unite::class);
    }

    public function paiements(): HasMany
    {
        return $this->hasMany(Paiement::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'client_id');
    }
}
