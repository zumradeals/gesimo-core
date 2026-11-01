<?php

namespace App\Sejours\Models;

use App\Noyau\Models\Utilisateur;
use App\Sejours\Enums\TypePiece;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pièce d'identité d'un voyageur, gardée dans son profil et réutilisée à chaque réservation. */
class PieceVoyageur extends Model
{
    protected $table = 'piece_voyageur';

    protected $fillable = ['utilisateur_id', 'type', 'chemin', 'mime', 'valide_jusqu'];

    protected $hidden = ['chemin'];

    protected function casts(): array
    {
        return ['valide_jusqu' => 'date', 'type' => TypePiece::class];
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class);
    }

    /** Une pièce dont la date de fin de validité est passée n'est plus acceptée. */
    public function estExpiree(): bool
    {
        return $this->valide_jusqu !== null && $this->valide_jusqu->startOfDay()->lt(now()->startOfDay());
    }
}
