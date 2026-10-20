<?php

namespace App\Sejours\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unite extends Model
{
    protected $table = 'unite';

    protected $fillable = ['residence_id', 'nom', 'type', 'capacite', 'active'];

    protected $attributes = ['active' => true];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'capacite' => 'integer'];
    }

    public function tarifs(): HasMany
    {
        return $this->hasMany(Tarif::class);
    }

    public function equipements(): BelongsToMany
    {
        return $this->belongsToMany(Equipement::class, 'unite_equipement');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class)->orderBy('ordre');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function residence(): BelongsTo
    {
        return $this->belongsTo(Residence::class);
    }
}
