<?php

namespace App\Parametres\Models;

use App\Noyau\Models\Utilisateur;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParametreHistorique extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'parametre_historique';

    public function parametre(): BelongsTo
    {
        return $this->belongsTo(Parametre::class);
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'modifie_par');
    }

    protected $fillable = ['parametre_id', 'ancienne_valeur', 'nouvelle_valeur', 'est_secret', 'modifie_par'];
}
