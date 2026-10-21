<?php

namespace App\Sejours\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentGerant extends Model
{
    protected $table = 'document_gerant';

    protected $fillable = ['profil_gerant_id', 'type', 'chemin', 'statut', 'motif_refus', 'verifie_par', 'verifie_le', 'remplace_le', 'source_document_id'];

    protected $attributes = ['statut' => 'en_attente'];

    protected $hidden = ['chemin'];

    protected function casts(): array
    {
        return ['verifie_le' => 'datetime', 'remplace_le' => 'datetime'];
    }

    public function profil(): BelongsTo
    {
        return $this->belongsTo(ProfilGerant::class, 'profil_gerant_id');
    }

    public function libelle(): string
    {
        return $this->type === 'identite' ? 'Pièce d’identité' : 'Certificat de résidence';
    }
}
