<?php

namespace App\Sejours\Models;

use App\Noyau\Models\Utilisateur;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ProfilGerant extends Model
{
    protected $table = 'profil_gerant';

    protected $fillable = ['utilisateur_id', 'slug', 'nom_complet', 'adresse', 'entreprise', 'nom_public', 'commune_publique', 'presentation', 'logo_chemin', 'reprise_a_revoir'];

    protected $hidden = ['adresse', 'nom_complet', 'utilisateur_id'];

    protected function casts(): array
    {
        return ['adresse' => 'encrypted', 'reprise_a_revoir' => 'boolean'];
    }

    public static function pour(Utilisateur $u): self
    {
        return self::firstOrCreate(['utilisateur_id' => $u->id], ['slug' => Str::random(24), 'nom_complet' => $u->nom, 'nom_public' => $u->nom]);
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(DocumentGerant::class);
    }

    public function scopeIdentiteValidee(Builder $q): void
    {
        $q->where('reprise_a_revoir', false)->whereHas('documents', fn ($d) => $d->where('type', 'identite')->where('statut', 'accepte')->whereNull('remplace_le'));
    }

    public function identiteValidee(): bool
    {
        return ! $this->reprise_a_revoir && $this->documentValide('identite') !== null;
    }

    public function estCertifie(): bool
    {
        return $this->identiteValidee() && $this->documentValide('certificat_residence') !== null;
    }

    public function documentValide(string $type): ?DocumentGerant
    {
        return $this->documents()->where('type', $type)->where('statut', 'accepte')->whereNull('remplace_le')->first();
    }

    public function dernierDocument(string $type): ?DocumentGerant
    {
        return $this->documents()->where('type', $type)->whereNull('remplace_le')->latest('id')->first();
    }
}
