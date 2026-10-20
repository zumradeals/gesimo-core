<?php

namespace App\Sejours\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Photo extends Model
{
    protected $table = 'photo';

    protected $fillable = ['residence_id', 'unite_id', 'chemin', 'ordre'];

    public function url(): string
    {
        return Storage::disk('public')->url($this->chemin);
    }
}
