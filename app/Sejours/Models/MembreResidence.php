<?php

namespace App\Sejours\Models;

use App\Noyau\Models\Utilisateur;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MembreResidence extends Model
{
    protected $table = 'membre_residence';

    protected $fillable = ['residence_id', 'utilisateur_id', 'peut_gerer_checkin_checkout', 'peut_gerer_calendrier'];

    protected function casts(): array
    {
        return [
            'peut_gerer_checkin_checkout' => 'boolean',
            'peut_gerer_calendrier' => 'boolean',
        ];
    }

    public function residence(): BelongsTo
    {
        return $this->belongsTo(Residence::class);
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class);
    }
}
