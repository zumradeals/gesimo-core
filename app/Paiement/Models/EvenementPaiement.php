<?php

namespace App\Paiement\Models;

use Illuminate\Database\Eloquent\Model;

class EvenementPaiement extends Model
{
    public $timestamps = false;

    protected $table = 'evenement_paiement';

    protected $fillable = ['reference', 'evenement', 'environnement', 'signature_valide', 'corps', 'resultat'];

    protected function casts(): array
    {
        return ['signature_valide' => 'boolean', 'corps' => 'array'];
    }
}
