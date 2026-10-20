<?php

namespace App\Sejours\Livewire;

use App\Noyau\Services\Audit;
use App\Paiement\Services\PlancherAcompte;
use App\Sejours\Models\DocumentVerification;
use App\Sejours\Models\Equipement;
use App\Sejours\Models\Photo;
use App\Sejours\Models\Residence;
use App\Sejours\Models\Unite;
use App\Sejours\Services\Photos;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Title('Ma résidence')]
class EditerResidence extends Component
{
    use WithFileUploads;

    public ?int $residenceId = null;

    // Résidence
    public string $nom = '';

    public string $commune = '';

    public string $quartier = '';

    public string $latitude = '';

    public string $longitude = '';

    public string $reglement = '';

    public string $modePaiement = 'total';

    public string $pourcentageAcompte = '';

    public string $politiqueAnnulation = 'flexible';

    // Logement en cours de saisie
    public bool $formulaireLogement = false;

    public ?int $uniteId = null;

    public string $uNom = '';

    public string $uType = 'chambre';

    public string $uCapacite = '2';

    public string $uPrix = '';

    public string $uPrixWeekEnd = '';

    /** @var list<array{libelle: string, debut: string, fin: string, prix: string}> */
    public array $uFetes = [];

    /** @var list<int|string> */
    public array $uEquipements = [];

    /** @var list<mixed> */
    public array $nouvellesPhotos = [];

    // Pièces
    public $pieceGerant = null;

    public $justificatif = null;

    public ?string $message = null;

    public ?string $erreur = null;

    public function mount(?Residence $residence = null): void
    {
        if ($residence?->exists) {
            Gate::authorize('update', $residence);
            $this->residenceId = $residence->id;
            $this->nom = $residence->nom;
            $this->commune = $residence->commune;
            $this->quartier = (string) $residence->quartier;
            $this->latitude = (string) $residence->latitude;
            $this->longitude = (string) $residence->longitude;
            $this->reglement = (string) $residence->reglement;
            $this->modePaiement = $residence->mode_paiement;
            $this->pourcentageAcompte = (string) $residence->pourcentage_acompte;
            $this->politiqueAnnulation = $residence->politique_annulation;
        } else {
            Gate::authorize('create', Residence::class);
        }
    }

    public function enregistrerResidence()
    {
        $this->reinitialiser();
        $plancher = PlancherAcompte::pourcentageMinimum();

        $donnees = $this->validate([
            'nom' => 'required|string|max:150',
            'commune' => 'required|string|max:100',
            'quartier' => 'nullable|string|max:100',
            'latitude' => 'nullable|numeric|between:-90,90|required_with:longitude',
            'longitude' => 'nullable|numeric|between:-180,180|required_with:latitude',
            'reglement' => 'nullable|string|max:3000',
            'modePaiement' => 'required|in:total,acompte',
            'pourcentageAcompte' => 'nullable|integer|between:1,100',
            'politiqueAnnulation' => 'required|in:flexible,stricte',
        ], [
            'latitude.required_with' => 'Indiquez aussi la longitude.',
            'longitude.required_with' => 'Indiquez aussi la latitude.',
        ], ['nom' => 'Le nom', 'commune' => 'La commune', 'pourcentageAcompte' => 'L\'acompte']);

        if ($this->modePaiement === 'acompte') {
            if ($plancher === null) {
                $this->addError('modePaiement', 'Le paiement par acompte n\'est pas encore disponible : tout est payé en ligne pour l\'instant.');

                return null;
            }
            if ($this->pourcentageAcompte === '' || (int) $this->pourcentageAcompte < $plancher) {
                $this->addError('pourcentageAcompte', "L'acompte ne peut pas être inférieur à {$plancher} %.");

                return null;
            }
        }

        $champs = [
            'nom' => trim($donnees['nom']),
            'commune' => trim($donnees['commune']),
            'quartier' => $donnees['quartier'] !== '' ? trim($donnees['quartier']) : null,
            'latitude' => $this->latitude !== '' ? $this->latitude : null,
            'longitude' => $this->longitude !== '' ? $this->longitude : null,
            'reglement' => $donnees['reglement'] !== '' ? $donnees['reglement'] : null,
            'mode_paiement' => $this->modePaiement,
            'pourcentage_acompte' => $this->modePaiement === 'acompte' ? (int) $this->pourcentageAcompte : null,
            'politique_annulation' => $this->politiqueAnnulation,
        ];

        if ($this->residenceId === null) {
            $residence = Residence::create($champs + ['gerant_id' => auth()->id()]);
            Audit::enregistrer('residence.creee', $residence);

            return redirect()->route('gerant.residence', $residence)->with('message', 'Résidence enregistrée. Ajoutez maintenant vos logements.');
        }

        $residence = $this->residence();
        $residence->update($champs);
        Audit::enregistrer('residence.modifiee', $residence);
        $this->message = 'Résidence enregistrée.';

        return null;
    }

    public function nouveauLogement(): void
    {
        $this->reinitialiser();
        $this->reinitialiserLogement();
        $this->formulaireLogement = true;
    }

    public function modifierLogement(int $id): void
    {
        $this->reinitialiser();
        $unite = $this->residence()->unites()->with(['tarifs', 'equipements'])->findOrFail($id);
        $this->reinitialiserLogement();
        $this->uniteId = $unite->id;
        $this->uNom = $unite->nom;
        $this->uType = $unite->type;
        $this->uCapacite = (string) $unite->capacite;
        $this->uPrix = (string) ($unite->tarifs->firstWhere('type_periode', 'standard')?->prix_nuit ?? '');
        $this->uPrixWeekEnd = (string) ($unite->tarifs->firstWhere('type_periode', 'week_end')?->prix_nuit ?? '');
        $this->uFetes = $unite->tarifs->where('type_periode', 'fete')->map(fn ($t) => [
            'libelle' => (string) $t->libelle, 'debut' => $t->date_debut->toDateString(), 'fin' => $t->date_fin->toDateString(), 'prix' => (string) $t->prix_nuit,
        ])->values()->all();
        $this->uEquipements = $unite->equipements->pluck('id')->all();
        $this->formulaireLogement = true;
    }

    public function ajouterFete(): void
    {
        $this->uFetes[] = ['libelle' => '', 'debut' => '', 'fin' => '', 'prix' => ''];
    }

    public function retirerFete(int $i): void
    {
        unset($this->uFetes[$i]);
        $this->uFetes = array_values($this->uFetes);
    }

    public function enregistrerLogement(): void
    {
        $this->reinitialiser();
        $residence = $this->residence();

        // Les prix sont saisis comme « 15 000 » : on garde seulement les chiffres.
        $this->uPrix = preg_replace('/\D+/', '', $this->uPrix);
        $this->uPrixWeekEnd = preg_replace('/\D+/', '', $this->uPrixWeekEnd);
        foreach ($this->uFetes as $i => $fete) {
            $this->uFetes[$i]['prix'] = preg_replace('/\D+/', '', $fete['prix']);
        }

        $this->validate([
            'uNom' => 'required|string|max:150',
            'uType' => 'required|in:chambre,studio,appartement',
            'uCapacite' => 'required|integer|min:1|max:50',
            'uPrix' => 'required|integer|min:1',
            'uPrixWeekEnd' => 'nullable|integer|min:1',
            'uFetes.*.libelle' => 'nullable|string|max:100',
            'uFetes.*.debut' => 'required|date',
            'uFetes.*.fin' => 'required|date|after_or_equal:uFetes.*.debut',
            'uFetes.*.prix' => 'required|integer|min:1',
            'uEquipements' => 'array',
            'nouvellesPhotos.*' => 'image|mimes:jpg,jpeg,png,webp|max:5120',
        ], [
            'uPrix.required' => 'Le prix par nuit est obligatoire et doit être un nombre entier.',
            'uPrix.integer' => 'Le prix par nuit est obligatoire et doit être un nombre entier.',
            'nouvellesPhotos.*.image' => 'Cette photo est trop lourde ou n\'est pas une image.',
            'nouvellesPhotos.*.max' => 'Cette photo est trop lourde ou n\'est pas une image.',
            'nouvellesPhotos.*.mimes' => 'Cette photo est trop lourde ou n\'est pas une image.',
            'uFetes.*.fin.after_or_equal' => 'La fin doit être le même jour ou après le début.',
        ], [
            'uNom' => 'Le nom', 'uCapacite' => 'Le nombre de personnes', 'uPrix' => 'Le prix par nuit', 'uPrixWeekEnd' => 'Le prix week-end',
            'uFetes.*.debut' => 'Le début', 'uFetes.*.fin' => 'La fin', 'uFetes.*.prix' => 'Le prix',
        ]);

        DB::transaction(function () use ($residence) {
            $unite = $this->uniteId
                ? $residence->unites()->findOrFail($this->uniteId)
                : new Unite(['residence_id' => $residence->id]);
            $unite->fill(['nom' => trim($this->uNom), 'type' => $this->uType, 'capacite' => (int) $this->uCapacite, 'active' => true])->save();

            $unite->tarifs()->updateOrCreate(['type_periode' => 'standard'], ['prix_nuit' => (int) $this->uPrix, 'libelle' => null]);
            if ($this->uPrixWeekEnd !== '') {
                $unite->tarifs()->updateOrCreate(['type_periode' => 'week_end'], ['prix_nuit' => (int) $this->uPrixWeekEnd, 'libelle' => null]);
            } else {
                $unite->tarifs()->where('type_periode', 'week_end')->delete();
            }
            $unite->tarifs()->where('type_periode', 'fete')->delete();
            foreach ($this->uFetes as $fete) {
                $unite->tarifs()->create([
                    'type_periode' => 'fete', 'libelle' => $fete['libelle'] ?: null,
                    'prix_nuit' => (int) $fete['prix'], 'date_debut' => $fete['debut'], 'date_fin' => $fete['fin'],
                ]);
            }
            $unite->equipements()->sync(array_map('intval', $this->uEquipements));

            foreach ($this->nouvellesPhotos as $fichier) {
                try {
                    Photos::enregistrer($fichier, $residence, $unite);
                } catch (InvalidArgumentException $e) {
                    throw ValidationException::withMessages(['nouvellesPhotos' => $e->getMessage()]);
                }
            }
            Audit::enregistrer($this->uniteId ? 'logement.modifie' : 'logement.cree', $unite, ['residence_id' => $residence->id]);
        });

        $this->message = 'Logement enregistré.';
        $this->formulaireLogement = false;
        $this->reinitialiserLogement();
    }

    public function supprimerPhoto(int $id): void
    {
        $photo = Photo::where('residence_id', $this->residence()->id)->findOrFail($id);
        Photos::supprimer($photo);
    }

    public function desactiverLogement(int $id): void
    {
        $this->residence()->unites()->findOrFail($id)->update(['active' => false]);
        $this->message = 'Logement retiré de la location.';
    }

    public function reactiverLogement(int $id): void
    {
        $this->residence()->unites()->findOrFail($id)->update(['active' => true]);
    }

    public function envoyerPieces(): void
    {
        $this->reinitialiser();
        $residence = $this->residence();
        $this->validate([
            'pieceGerant' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'justificatif' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ], [], ['pieceGerant' => 'La pièce d\'identité', 'justificatif' => 'Le justificatif']);

        foreach (['piece_gerant' => 'pieceGerant', 'justificatif_droit_gestion' => 'justificatif'] as $type => $propriete) {
            if ($this->{$propriete}) {
                // Stockage privé, nom au hasard : jamais accessible par une adresse publique.
                $chemin = $this->{$propriete}->store("documents/{$residence->id}", 'local');
                DocumentVerification::updateOrCreate(['residence_id' => $residence->id, 'type' => $type], ['chemin' => $chemin, 'statut' => 'en_attente', 'verifie_par' => null, 'verifie_le' => null]);
                $this->{$propriete} = null;
            }
        }
        $this->message = 'Pièces enregistrées.';
    }

    public function soumettre(): void
    {
        $this->reinitialiser();
        $residence = $this->residence()->load(['unites.tarifs', 'photos', 'documents']);

        $manques = [];
        if (! $residence->unites->contains(fn ($u) => $u->active && $u->tarifs->contains('type_periode', 'standard'))) {
            $manques[] = 'au moins un logement avec son prix par nuit';
        }
        if ($residence->photos->isEmpty()) {
            $manques[] = 'au moins une photo';
        }
        foreach (['piece_gerant' => 'la pièce d\'identité du gérant', 'justificatif_droit_gestion' => 'le justificatif du droit de gérer'] as $type => $libelle) {
            if (! $residence->documents->contains('type', $type)) {
                $manques[] = $libelle;
            }
        }
        if ($manques !== []) {
            $this->erreur = 'Il manque : '.implode(', ', $manques).'.';

            return;
        }

        $residence->update(['statut_verification' => 'en_attente', 'soumise_le' => now(), 'motif_refus' => null]);
        Audit::enregistrer('residence.soumise', $residence);
        $this->message = 'Envoyée pour vérification. Vous serez prévenu dès la décision de l\'équipe GESIMO.';
    }

    public function updatedNouvellesPhotos(): void
    {
        // Les erreurs de photo s'affichent dès le choix des fichiers.
        $this->validateOnly('nouvellesPhotos.*', [
            'nouvellesPhotos.*' => 'image|mimes:jpg,jpeg,png,webp|max:5120',
        ], [
            'nouvellesPhotos.*.image' => 'Cette photo est trop lourde ou n\'est pas une image.',
            'nouvellesPhotos.*.max' => 'Cette photo est trop lourde ou n\'est pas une image.',
            'nouvellesPhotos.*.mimes' => 'Cette photo est trop lourde ou n\'est pas une image.',
        ]);
    }

    public function render()
    {
        $residence = $this->residenceId ? $this->residence()->load(['unites.tarifs', 'unites.photos', 'unites.equipements', 'documents']) : null;

        return view('livewire.sejours.editer-residence', [
            'residence' => $residence,
            'equipements' => Equipement::orderBy('id')->get(),
            'plancher' => PlancherAcompte::pourcentageMinimum(),
            'maxPhotos' => Photos::MAX_PAR_RESIDENCE,
            'nbPhotos' => $residence ? Photo::where('residence_id', $residence->id)->count() : 0,
        ]);
    }

    private function residence(): Residence
    {
        $residence = Residence::findOrFail($this->residenceId);
        Gate::authorize('update', $residence);

        return $residence;
    }

    private function reinitialiser(): void
    {
        $this->message = null;
        $this->erreur = null;
        $this->resetErrorBag();
    }

    private function reinitialiserLogement(): void
    {
        $this->uniteId = null;
        $this->uNom = '';
        $this->uType = 'chambre';
        $this->uCapacite = '2';
        $this->uPrix = '';
        $this->uPrixWeekEnd = '';
        $this->uFetes = [];
        $this->uEquipements = [];
        $this->nouvellesPhotos = [];
    }
}
