<?php

namespace App\Noyau\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    protected $table = 'notification';

    protected $fillable = ['destinataire_id', 'canal', 'sujet', 'contenu', 'statut', 'envoye_le', 'erreur', 'modele', 'reservation_id'];

    protected $attributes = ['canal' => 'email', 'statut' => 'en_attente'];

    protected function casts(): array
    {
        return ['envoye_le' => 'datetime'];
    }

    public function destinataire(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'destinataire_id');
    }
}
