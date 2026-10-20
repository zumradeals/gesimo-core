<?php

namespace App\Parametres\Models;

use Illuminate\Database\Eloquent\Model;

class ParametreHistorique extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'parametre_historique';

    protected $fillable = ['parametre_id', 'ancienne_valeur', 'nouvelle_valeur', 'est_secret', 'modifie_par'];
}
