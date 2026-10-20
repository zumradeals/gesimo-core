<?php

namespace App\Noyau\Models;

use Illuminate\Database\Eloquent\Model;

class JetonConnexion extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'jeton_connexion';

    protected $fillable = ['utilisateur_id', 'type', 'empreinte', 'expire_le', 'utilise_le', 'tentatives'];

    protected function casts(): array
    {
        return ['expire_le' => 'datetime', 'utilise_le' => 'datetime'];
    }
}
