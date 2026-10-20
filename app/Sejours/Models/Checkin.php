<?php

namespace App\Sejours\Models;

use Illuminate\Database\Eloquent\Model;

class Checkin extends Model
{
    protected $table = 'checkin';

    protected $fillable = ['reservation_id', 'arrivee_le', 'enregistre_par', 'depart_le', 'depart_enregistre_par', 'piece_type', 'piece_numero', 'piece_chemin'];

    protected function casts(): array
    {
        return ['arrivee_le' => 'datetime', 'depart_le' => 'datetime', 'piece_numero' => 'encrypted'];
    }
}
