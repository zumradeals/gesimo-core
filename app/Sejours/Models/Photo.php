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

    public static function cheminMiniature(string $chemin): string
    {
        return preg_replace('/\.jpg$/', '_m.jpg', $chemin);
    }

    /** Version légère pour les listes ; les anciennes photos (sans miniature) gardent l'image d'origine. */
    public function urlMiniature(): string
    {
        $mini = self::cheminMiniature($this->chemin);

        return $mini !== $this->chemin && Storage::disk('public')->exists($mini) ? Storage::disk('public')->url($mini) : $this->url();
    }
}
