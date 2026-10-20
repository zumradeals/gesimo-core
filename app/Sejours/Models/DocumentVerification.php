<?php

namespace App\Sejours\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentVerification extends Model
{
    protected $table = 'document_verification';

    protected $fillable = ['residence_id', 'type', 'chemin', 'statut', 'verifie_par', 'verifie_le'];

    public function libelle(): string
    {
        return $this->type === 'piece_gerant' ? 'Pièce d\'identité du gérant' : 'Justificatif du droit de gérer';
    }
}
