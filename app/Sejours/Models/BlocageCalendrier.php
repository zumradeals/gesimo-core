<?php

namespace App\Sejours\Models;

use Illuminate\Database\Eloquent\Model;

class BlocageCalendrier extends Model
{
    protected $table = 'blocage_calendrier';

    protected $fillable = ['unite_id', 'date_debut', 'date_fin', 'motif', 'cree_par'];

    protected function casts(): array
    {
        return ['date_debut' => 'date', 'date_fin' => 'date'];
    }
}
