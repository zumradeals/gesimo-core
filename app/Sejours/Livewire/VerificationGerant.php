<?php

namespace App\Sejours\Livewire;

use App\Noyau\Enums\Role;
use App\Sejours\Models\ProfilGerant;
use App\Sejours\Models\Residence;
use App\Sejours\Services\DossierGerant;
use App\Sejours\Services\PiecesVerification;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

/** « Ma vérification » : deux documents seulement, la pièce d'identité (une fois) et le contrat de bail de chaque résidence. */
#[Title('Ma vérification')]
class VerificationGerant extends Component
{
    use WithFileUploads;

    public $identite = null;

    /** @var array<int, mixed> Un fichier de bail par résidence. */
    public array $baux = [];

    public ?string $message = null;

    public int $version = 0;

    public function deposerIdentite(): void
    {
        $profil = $this->profil();
        $this->message = null;
        if ($this->identite === null) {
            $this->addError('identite', 'Choisissez d’abord la photo ou le fichier de votre pièce d’identité.');

            return;
        }
        DossierGerant::enregistrer($profil, ['identite' => $this->identite]);
        $this->reset('identite');
        $this->version++;
        $this->message = 'Pièce d’identité envoyée. L’équipe Avelis la contrôle sous quelques jours.';
    }

    public function deposerBail(int $residenceId): void
    {
        $this->profil();
        $residence = Residence::where('gerant_id', auth()->id())->findOrFail($residenceId);
        $this->message = null;
        if (empty($this->baux[$residenceId])) {
            $this->addError("baux.$residenceId", 'Choisissez d’abord la photo ou le fichier du contrat de bail.');

            return;
        }
        $this->validate(["baux.$residenceId" => 'file|mimes:pdf,jpg,jpeg,png|max:5120'], [
            "baux.$residenceId.mimes" => 'Choisissez un document PDF, JPEG ou PNG.',
            "baux.$residenceId.max" => 'Le document ne doit pas dépasser 5 Mo.',
            "baux.$residenceId.file" => 'Choisissez un document PDF, JPEG ou PNG.',
        ]);
        PiecesVerification::enregistrer($residence, ['justificatif_droit_gestion' => $this->baux[$residenceId]]);
        unset($this->baux[$residenceId]);
        $this->version++;
        $this->message = 'Contrat de bail envoyé pour « '.$residence->nom.' ». L’équipe Avelis le contrôle sous quelques jours.';
    }

    private function profil(): ProfilGerant
    {
        abort_unless(auth()->user()?->estActif() && auth()->user()->aRole(Role::Gerant), 403);
        $profil = ProfilGerant::pour(auth()->user());
        DossierGerant::autoriserProprietaire($profil);

        return $profil;
    }

    public function render()
    {
        $profil = $this->profil();
        $residences = Residence::where('gerant_id', auth()->id())->with('documents')->orderBy('nom')->get();

        return view('livewire.sejours.verification-gerant', [
            'profil' => $profil,
            'identiteDernier' => $profil->dernierDocument('identite'),
            'residences' => $residences,
            'verifie' => $profil->estVerifie(),
        ]);
    }
}
