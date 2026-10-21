<?php

namespace App\Sejours\Livewire\Admin;

use App\Sejours\Models\ProfilGerant;
use App\Sejours\Services\DossierGerant;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Vérifier le dossier du gérant')]
class VerifierGerant extends Component
{
    #[Locked]
    public int $profilId;

    public string $motif = '';

    public ?string $message = null;

    public function mount(ProfilGerant $profil): void
    {
        Gate::authorize('voir-verifications');
        $this->profilId = $profil->id;
    }

    public function accepter(int $id): void
    {
        $this->message = null;
        $this->resetErrorBag();
        DossierGerant::decider($this->profilId, $id, true);
        $this->message = 'Document validé. Les droits de publication et la certification ont été actualisés.';
    }

    public function refuser(int $id): void
    {
        $this->message = null;
        $this->validate(['motif' => 'required|string|max:1000']);
        DossierGerant::decider($this->profilId, $id, false, $this->motif);
        $this->motif = '';
        $this->message = 'Document refusé. Le motif est visible par le gérant.';
    }

    public function render()
    {
        Gate::authorize('voir-verifications');
        $p = ProfilGerant::with('utilisateur')->findOrFail($this->profilId);

        return view('livewire.admin.verifier-gerant', ['p' => $p, 'documents' => $p->documents()->latest('id')->get()]);
    }
}
