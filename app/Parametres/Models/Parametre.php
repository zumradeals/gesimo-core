<?php

namespace App\Parametres\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Parametre extends Model
{
    protected $table = 'parametre';

    protected $fillable = ['cle', 'valeur', 'est_secret', 'groupe', 'modifie_par'];

    /** La valeur brute (texte chiffré pour un secret) n'est jamais exportée avec le modèle. */
    protected $hidden = ['valeur'];

    protected function casts(): array
    {
        return ['est_secret' => 'boolean'];
    }

    public function historique(): HasMany
    {
        return $this->hasMany(ParametreHistorique::class);
    }
}
