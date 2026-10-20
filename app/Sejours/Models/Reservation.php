<?php

namespace App\Sejours\Models;

use App\Noyau\Models\Utilisateur;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reservation extends Model
{
    protected $table = 'reservation';

    protected $fillable = [
        'unite_id', 'client_id', 'nom_client', 'telephone_client', 'date_arrivee', 'date_depart',
        'montant_total', 'acompte', 'solde', 'statut', 'origine', 'expire_le', 'cree_par',
    ];

    protected function casts(): array
    {
        return [
            'date_arrivee' => 'date',
            'date_depart' => 'date',
            'expire_le' => 'datetime',
            'montant_total' => 'integer',
            'acompte' => 'integer',
            'solde' => 'integer',
        ];
    }

    public function unite(): BelongsTo
    {
        return $this->belongsTo(Unite::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'client_id');
    }
}
