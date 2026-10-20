<?php

namespace App\Paiement\Models;

use App\Noyau\Models\Utilisateur;
use App\Sejours\Models\Reservation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** En ajout seul : la base refuse toute modification et toute suppression. */
class EcritureComptable extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'ecriture_comptable';

    protected $fillable = [
        'reservation_id', 'paiement_id', 'mise_en_avant_id', 'reversement_id', 'remboursement_id',
        'type', 'montant', 'correction_de', 'motif', 'cree_par',
    ];

    protected function casts(): array
    {
        return ['montant' => 'integer'];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'cree_par');
    }

    public static function libelleType(string $type): string
    {
        return match ($type) {
            'encaissement' => 'Encaissement',
            'commission' => 'Commission',
            'part_gerant' => 'Part du gérant',
            'remboursement' => 'Remboursement',
            'reversement' => 'Reversement',
            'mise_en_avant' => 'Mise en avant',
            default => $type,
        };
    }
}
