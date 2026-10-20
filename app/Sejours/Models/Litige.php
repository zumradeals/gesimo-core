<?php

namespace App\Sejours\Models;

use App\Noyau\Models\Utilisateur;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Litige extends Model
{
    protected $table = 'litige';

    protected $fillable = ['reservation_id', 'ouvert_par', 'partie_adverse_id', 'motif', 'decision', 'decide_par', 'decide_le', 'statut', 'statut_avant'];

    protected $attributes = ['statut' => 'ouvert'];

    protected function casts(): array
    {
        return ['decide_le' => 'datetime'];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'ouvert_par');
    }

    public function partieAdverse(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'partie_adverse_id');
    }

    public function estOuvert(): bool
    {
        return $this->statut === 'ouvert';
    }
}
