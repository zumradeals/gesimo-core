<?php

namespace App\Sejours\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvitationPersonnel extends Model
{
    protected $table = 'invitation_personnel';

    protected $fillable = [
        'residence_id', 'invite_par', 'nom', 'email', 'telephone', 'peut_gerer_checkin_checkout',
        'peut_gerer_calendrier', 'empreinte', 'expire_le', 'acceptee_le', 'utilisateur_id',
    ];

    protected function casts(): array
    {
        return [
            'peut_gerer_checkin_checkout' => 'boolean',
            'peut_gerer_calendrier' => 'boolean',
            'expire_le' => 'datetime',
            'acceptee_le' => 'datetime',
        ];
    }

    public function residence(): BelongsTo
    {
        return $this->belongsTo(Residence::class);
    }

    public function estValide(): bool
    {
        return $this->acceptee_le === null && $this->expire_le->isFuture();
    }
}
