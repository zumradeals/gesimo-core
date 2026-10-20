<?php

namespace App\Sejours\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Unite extends Model
{
    protected $table = 'unite';

    protected $fillable = ['residence_id', 'nom', 'type', 'capacite', 'active'];

    public function residence(): BelongsTo
    {
        return $this->belongsTo(Residence::class);
    }
}
