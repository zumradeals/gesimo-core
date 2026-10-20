<?php

namespace App\Sejours\Models;

use Illuminate\Database\Eloquent\Model;

class Tarif extends Model
{
    protected $table = 'tarif';

    protected $fillable = ['unite_id', 'type_periode', 'libelle', 'prix_nuit', 'date_debut', 'date_fin'];

    protected function casts(): array
    {
        return ['prix_nuit' => 'integer', 'date_debut' => 'date', 'date_fin' => 'date'];
    }
}
