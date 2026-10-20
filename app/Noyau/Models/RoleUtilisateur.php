<?php

namespace App\Noyau\Models;

use App\Noyau\Enums\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoleUtilisateur extends Model
{
    protected $table = 'role_utilisateur';

    protected $fillable = ['utilisateur_id', 'role'];

    protected function casts(): array
    {
        return ['role' => Role::class];
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class);
    }
}
