<?php

namespace App\Sejours\Livewire;

use App\Noyau\Enums\Role;
use App\Noyau\Services\Audit;
use App\Noyau\Services\Telephone;
use App\Sejours\Models\ProfilGerant as Profil;
use App\Sejours\Services\DossierGerant;
use App\Sejours\Services\LogoGerant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Throwable;

#[Title('Mon profil gérant')]
class ProfilGerant extends Component
{
    use WithFileUploads;

    public string $onglet = 'documents';

    public string $nomComplet = '';

    public string $adresse = '';

    public string $telephone = '';

    public string $email = '';

    public string $entreprise = '';

    public string $nomPublic = '';

    public string $communePublique = '';

    public string $presentation = '';

    public $identite = null;

    public $certificat_residence = null;

    public $logo = null;

    public int $versionFichiers = 0;

    public ?string $message = null;

    public function mount(): void
    {
        $p = $this->profil();
        $this->nomComplet = $p->nom_complet;
        $this->adresse = (string) $p->adresse;
        $this->entreprise = (string) $p->entreprise;
        $this->nomPublic = $p->nom_public;
        $this->communePublique = (string) $p->commune_publique;
        $this->presentation = (string) $p->presentation;
        $this->telephone = auth()->user()->telephone;
        $this->email = (string) auth()->user()->email;
    }

    public function enregistrer(): void
    {
        $p = $this->profil();
        $this->message = null;
        $this->nomComplet = trim($this->nomComplet);
        $this->nomPublic = trim($this->nomPublic);
        $this->adresse = trim($this->adresse);
        $this->telephone = Telephone::normaliser($this->telephone);
        $this->email = mb_strtolower(trim($this->email));
        $this->validate([
            'nomComplet' => 'required|string|max:120', 'adresse' => 'required|string|max:1000',
            'entreprise' => 'nullable|string|max:150', 'nomPublic' => 'required|string|max:150',
            'communePublique' => 'nullable|string|max:100', 'presentation' => 'nullable|string|max:2000',
            'telephone' => ['required', fn ($a, $v, $e) => Telephone::estValide($v) ? null : $e('Le numéro doit avoir 10 chiffres.'), Rule::unique('utilisateur', 'telephone')->ignore(auth()->id())],
            'email' => ['required', 'email', 'max:190', Rule::unique('utilisateur', 'email')->ignore(auth()->id())],
            'logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ], [], ['nomComplet' => 'Le nom complet', 'nomPublic' => 'Le nom public', 'adresse' => 'Votre adresse']);
        if ($p->identiteValidee() && $this->nomComplet !== $p->nom_complet) {
            throw ValidationException::withMessages(['nomComplet' => 'Votre nom correspond à l’identité validée. Contactez Avelis pour le faire corriger.']);
        }
        $nouveauLogo = $this->logo ? LogoGerant::enregistrer($this->logo, $p) : null;
        try {
            DB::transaction(function () use ($p, $nouveauLogo) {
                $p->update([
                    'nom_complet' => $this->nomComplet, 'adresse' => $this->adresse,
                    'entreprise' => trim($this->entreprise) ?: null, 'nom_public' => $this->nomPublic,
                    'commune_publique' => trim($this->communePublique) ?: null, 'presentation' => trim($this->presentation) ?: null,
                    'logo_chemin' => $nouveauLogo ?? $p->logo_chemin,
                ]);
                auth()->user()->update(['telephone' => $this->telephone, 'email' => $this->email]);
                Audit::enregistrer('gerant.profil_modifie', $p);
            });
        } catch (Throwable $e) {
            if ($nouveauLogo) {
                Storage::disk('public')->delete($nouveauLogo);
            }
            throw $e;
        }
        $this->reset('logo');
        $this->versionFichiers++;
        $this->message = 'Votre profil est enregistré.';
    }

    public function envoyerDocuments(): void
    {
        $p = $this->profil();
        $this->message = null;
        $this->resetErrorBag();
        $fichiers = array_filter(['identite' => $this->identite, 'certificat_residence' => $this->certificat_residence]);
        if ($fichiers === []) {
            $this->message = 'Aucun nouveau document sélectionné. Votre dossier est conservé.';

            return;
        }
        DossierGerant::enregistrer($p, $fichiers);
        $this->reset('identite', 'certificat_residence');
        $this->versionFichiers++;
        $this->message = 'Documents envoyés pour contrôle. Vous pouvez suivre leur état ici.';
    }

    private function profil(): Profil
    {
        abort_unless(auth()->user()?->estActif() && auth()->user()->aRole(Role::Gerant), 403);
        $p = Profil::pour(auth()->user());
        DossierGerant::autoriserProprietaire($p);

        return $p;
    }

    public function render()
    {
        $p = $this->profil();

        return view('livewire.sejours.profil-gerant', ['profil' => $p, 'documents' => $p->documents()->whereNull('remplace_le')->latest('id')->get()]);
    }
}
