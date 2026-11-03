<?php

namespace App\Sejours\Livewire;

use App\Noyau\Services\Audit;
use App\Sejours\Models\Residence;
use App\Sejours\Services\Itineraire;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/** « Accès à la résidence » : position, adresse écrite et repères. Rien de public : le voyageur les reçoit après confirmation. */
#[Title('Accès à la résidence')]
class AccesResidence extends Component
{
    /** Au-delà de cette distance, déplacer une résidence déjà validée la fait revoir par Avelis (en dessous : simple imprécision du GPS). */
    public const DEPLACEMENT_MAX_METRES = 500;

    #[Locked]
    public int $residenceId;

    public string $latitude = '';

    public string $longitude = '';

    public string $lien = '';

    public string $adresse = '';

    public string $reperes = '';

    public ?string $message = null;

    public ?string $erreur = null;

    public function mount(int $residence): void
    {
        $r = $this->residence($residence);
        $this->residenceId = $r->id;
        $this->latitude = (string) ($r->latitude ?? '');
        $this->longitude = (string) ($r->longitude ?? '');
        $this->adresse = (string) $r->adresse;
        $this->reperes = (string) $r->reperes;
    }

    private function residence(?int $id = null): Residence
    {
        $r = Residence::findOrFail($id ?? $this->residenceId);
        Gate::authorize('update', $r);

        return $r;
    }

    /** Appelée par le téléphone (position actuelle) : la résidence est placée là où se trouve le gérant. */
    public function positionRecue(float $latitude, float $longitude): void
    {
        $this->erreur = $this->message = null;
        if (abs($latitude) > 90 || abs($longitude) > 180) {
            $this->erreur = 'Nous n’avons pas pu lire votre position. Autorisez la localisation dans votre navigateur, ou collez un lien Google Maps.';

            return;
        }
        $this->latitude = (string) round($latitude, 6);
        $this->longitude = (string) round($longitude, 6);
        $this->lien = '';
        $this->message = 'Position relevée. Touchez « Enregistrer » pour la garder.';
    }

    public function positionRefusee(): void
    {
        $this->message = null;
        $this->erreur = 'Nous n’avons pas pu lire votre position. Autorisez la localisation dans votre navigateur, ou collez un lien Google Maps ci-dessous.';
    }

    public function lireLien(): void
    {
        $this->erreur = $this->message = null;
        $this->resetErrorBag('lien');
        if (trim($this->lien) === '') {
            return;
        }
        $position = Itineraire::positionDepuisLien($this->lien);
        if ($position === null) {
            $this->addError('lien', 'Ce lien ne contient pas de position. Ouvrez-le, puis copiez le lien complet de la page, ou utilisez « Ma position actuelle ».');

            return;
        }
        [$this->latitude, $this->longitude] = [(string) $position[0], (string) $position[1]];
        $this->lien = '';
        $this->message = 'Position lue dans le lien. Touchez « Enregistrer » pour la garder.';
    }

    public function retirerPosition(): void
    {
        $this->latitude = $this->longitude = '';
    }

    public function enregistrer(): void
    {
        $this->erreur = $this->message = null;
        $this->validate([
            'adresse' => 'nullable|string|max:255',
            'reperes' => 'nullable|string|max:300',
        ], [
            'reperes.max' => 'Vos repères sont trop longs (300 caractères au maximum).',
            'adresse.max' => 'L’adresse est trop longue (255 caractères au maximum).',
        ]);
        $r = $this->residence();
        $lat = $this->latitude === '' ? null : (float) $this->latitude;
        $lng = $this->longitude === '' ? null : (float) $this->longitude;

        $avait = Itineraire::aUnePosition($r);
        $deplacee = $avait && $lat !== null && Itineraire::distance((float) $r->latitude, (float) $r->longitude, $lat, $lng) > self::DEPLACEMENT_MAX_METRES;
        $r->update(['latitude' => $lat, 'longitude' => $lng, 'adresse' => trim($this->adresse) ?: null, 'reperes' => trim($this->reperes) ?: null]);
        Audit::enregistrer('residence.acces_modifie', $r, ['position' => $lat !== null]);

        if ($deplacee && $r->estVerifiee()) {
            $r->update(['statut_verification' => 'en_attente', 'badge_verifie' => false, 'soumise_le' => now(), 'motif_refus' => null]);
            Audit::enregistrer('residence.reverification_demandee', $r);
            $this->message = 'Accès enregistré. Comme la résidence a été déplacée de plus de '.self::DEPLACEMENT_MAX_METRES.' m, elle repasse en vérification : elle est invisible jusqu’à sa nouvelle validation (vos réservations confirmées ne changent pas).';

            return;
        }
        $this->message = 'Accès enregistré.';
    }

    public function render()
    {
        $r = $this->residence();
        $apercu = clone $r;
        $apercu->forceFill(['latitude' => $this->latitude === '' ? null : $this->latitude, 'longitude' => $this->longitude === '' ? null : $this->longitude, 'adresse' => $this->adresse, 'reperes' => $this->reperes]);

        return view('livewire.sejours.acces-residence', ['r' => $r, 'apercu' => $apercu, 'aPosition' => $this->latitude !== '' && $this->longitude !== '']);
    }
}
