<?php

namespace App\Sejours\Models;

use Illuminate\Database\Eloquent\Model;

class Equipement extends Model
{
    protected $table = 'equipement';

    protected $fillable = ['code', 'libelle'];
}
