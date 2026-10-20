<?php

namespace App\Noyau\Models;

use Illuminate\Database\Eloquent\Model;

/** En ajout seul : la base refuse toute modification ou suppression. */
class JournalAudit extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'journal_audit';

    protected $fillable = ['utilisateur_id', 'action', 'objet_type', 'objet_id', 'details', 'adresse_ip'];

    protected function casts(): array
    {
        return ['details' => 'array'];
    }
}
