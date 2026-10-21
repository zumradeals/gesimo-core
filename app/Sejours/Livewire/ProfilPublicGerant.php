<?php

namespace App\Sejours\Livewire;

use App\Sejours\Models\ProfilGerant;
use App\Sejours\Models\Unite;
use App\Sejours\Services\DossierGerant;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Votre hébergeur — Avelis Séjours')]
class ProfilPublicGerant extends Component
{
    use WithPagination;

    #[Locked]
    public string $slug;

    #[Locked]
    public bool $apercu = false;

    public function mount(?string $slug = null): void
    {
        $this->apercu = request()->routeIs('gerant.profil.apercu');
        if ($this->apercu) {
            abort_unless(auth()->check(), 403);
            $p = ProfilGerant::pour(auth()->user());
            DossierGerant::autoriserProprietaire($p);
            $slug = $p->slug;
        }
        $this->slug = (string) $slug;
    }

    public function render()
    {
        $p = ProfilGerant::with('utilisateur.roles')->where('slug', $this->slug)->firstOrFail();
        if ($this->apercu) {
            DossierGerant::autoriserProprietaire($p);
        }
        $unites = Unite::where('active', true)->whereHas('tarifs', fn ($q) => $q->where('type_periode', 'standard'))
            ->whereHas('residence', fn ($q) => $q->where('gerant_id', $p->utilisateur_id)->publiee())
            ->with(['photos', 'tarifs', 'residence.photos', 'residence.documents'])->orderByDesc('id')->paginate(12);
        abort_unless($this->apercu || $unites->total() > 0, 404);

        // Seules les données explicitement publiques sont transmises à la vue.
        return view('livewire.sejours.profil-public-gerant', [
            'nomPublic' => $p->nom_public, 'communePublique' => $p->commune_publique,
            'presentationPublique' => $p->presentation, 'logoChemin' => $p->logo_chemin,
            'certifie' => $p->estCertifie(), 'unites' => $unites,
        ]);
    }
}
