<?php

namespace App\Sejours\Livewire;

use App\Noyau\Services\Audit;
use App\Paiement\Services\PlancherAcompte;
use App\Sejours\Models\Equipement;
use App\Sejours\Models\Photo;
use App\Sejours\Models\Residence;
use App\Sejours\Models\Unite;
use App\Sejours\Services\Photos;
use App\Sejours\Services\PiecesVerification;
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

    /** Étape de l'assistant : 1 résidence, 2 logements, 3 pièces et envoi. */
    public int $etape = 1;

    // Résidence
    public string $nom = '';

    public string $commune = '';

    public string $quartier = '';

    public string $latitude = '';

    public string $longitude = '';

    public string $reglement = '';

    public string $modePaiement = 'total';

    public string $pourcentageAcompte = '';

    /** Seule la politique « flexible » existe pour l'instant : les règles d'une politique stricte ne sont pas définies. */
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
    public $justificatif = null;

    public int $versionPieces = 0;

    public ?string $message = null;

    public ?string $erreur = null;

    public function mount(?Residence $residence = null): void
    {
        if ($residence?->exists) {
            Gate::authorize('update', $residence);
            $this->residenceId = $residence->id;
            $this->etape = in_array((int) session('etape', 1), [1, 2], true) ? (int) session('etape', 1) : 1;
            $this->nom = $residence->nom;
            $this->commune = $residence->commune;
            $this->quartier = (string) $residence->quartier;
            $this->latitude = (string) $residence->latitude;
            $this->longitude = (string) $residence->longitude;
            $this->reglement = (string) $residence->reglement;
            $this->modePaiement = $residence->mode_paiement;
            $this->pourcentageAcompte = (string) $residence->pourcentage_acompte;
            $this->politiqueAnnulation = 'flexible';
        } else {
            Gate::authorize('create', Residence::class);
        }
    }

    public function allerEtape(int $etape): void
    {
        if ($this->residenceId === null || $etape < 1 || $etape > 3) {
            $this->etape = 1;

            return;
        }
        if ($etape > $this->etape) {
            // Même contrôle pour les onglets et les boutons « Étape suivante ».
            $this->etape = 1;
            $this->enregistrerResidence();
            if ($this->getErrorBag()->isNotEmpty()) {
                return;
            }
            if ($etape === 3 && ! $this->preparerLogements()) {
                return;
            }
        }
        $this->etape = $etape;
    }

    private function preparerLogements(): bool
    {
        $this->etape = 2;
        if ($this->formulaireLogement) {
            $this->enregistrerLogement();
        }
        $residence = $this->residence();
        if (! $residence->unites()->where('active', true)->whereHas('tarifs', fn ($q) => $q->where('type_periode', 'standard'))->exists()) {
            $this->erreur = 'Ajoutez et enregistrez au moins un logement avec son prix par nuit avant de continuer.';

            return false;
        }

        return true;
    }

    public function enregistrerResidence()
    {
        $this->reinitialiser();
        $this->nom = trim($this->nom);
        $this->commune = trim($this->commune);
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
            'politiqueAnnulation' => 'required|in:flexible',
        ], [
            'latitude.required_with' => 'Indiquez aussi la latitude.',
            'longitude.required_with' => 'Indiquez aussi la longitude.',
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

            return redirect()->route('gerant.residence', $residence)->with('message', 'Résidence enregistrée. Ajoutez maintenant vos logements.')->with('etape', 2);
        }

        $residence = $this->residence();
        $identiteModifiee = $this->identiteChange($residence, $champs);
        $residence->update($champs);
        Audit::enregistrer('residence.modifiee', $residence, ['identite_modifiee' => $identiteModifiee]);
        $this->message = 'Résidence enregistrée.'.($identiteModifiee ? $this->repasserEnVerification($residence) : '');

        return null;
    }

    public function nouveauLogement(): void
    {
        $this->etape = 2;
        $this->reinitialiser();
        $this->reinitialiserLogement();
        $this->formulaireLogement = true;
    }

    public function modifierLogement(int $id): void
    {
        $this->etape = 2;
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
        $this->etape = 2;
        $this->reinitialiser();
        $residence = $this->residence();

        $this->uNom = trim($this->uNom);
        // Seuls les espaces de groupement sont retirés : jamais le signe ni les décimales.
        $this->uPrix = preg_replace('/[ \x{00A0}\x{202F}]/u', '', trim($this->uPrix));
        $this->uPrixWeekEnd = preg_replace('/[ \x{00A0}\x{202F}]/u', '', trim($this->uPrixWeekEnd));
        foreach ($this->uFetes as $i => $fete) {
            $this->uFetes[$i]['prix'] = preg_replace('/[ \x{00A0}\x{202F}]/u', '', trim($fete['prix']));
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
            'uEquipements.*' => 'integer|exists:equipement,id',
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
        $this->etape = 3;
        $this->reinitialiser();
        $this->message = $this->enregistrerPieces($this->residence())
            ? 'Justificatif enregistré pour contrôle. La publication de l’annonce est indépendante du badge.'
            : 'Aucun nouveau justificatif sélectionné. Votre dossier est conservé.';
    }

    private function enregistrerPieces(Residence $residence): bool
    {
        $this->validate(['justificatif' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120'], [
            'justificatif.mimes' => 'Choisissez un justificatif en PDF, JPEG ou PNG.',
            'justificatif.max' => 'Le justificatif ne doit pas dépasser 5 Mo.',
        ], ['justificatif' => 'Le justificatif du droit de gérer']);
        $modifie = PiecesVerification::enregistrer($residence, $this->justificatif ? ['justificatif_droit_gestion' => $this->justificatif] : []);
        if ($modifie) {
            $this->reset('justificatif');
            $this->versionPieces++;
        }

        return $modifie;
    }

    public function soumettre()
    {
        $this->reinitialiser();
        $this->etape = 1;
        $this->enregistrerResidence();
        if ($this->getErrorBag()->isNotEmpty()) {
            return null;
        }
        $this->message = null;
        if (! $this->preparerLogements()) {
            return null;
        }
        $this->etape = 3;
        $residence = $this->residence();
        if (! auth()->user()->profilGerant?->identiteValidee()) {
            $this->erreur = 'Votre pièce d’identité doit être validée dans votre profil gérant avant l’envoi. Le certificat de résidence reste facultatif.';

            return null;
        }
        $this->enregistrerPieces($residence);
        if ($residence->photos()->doesntExist()) {
            $this->erreur = 'Il manque : au moins une photo.';

            return null;
        }
        $dejaPubliee = DB::transaction(function () use ($residence) {
            $residence = Residence::lockForUpdate()->findOrFail($residence->id);
            if ($residence->estVerifiee()) {
                return true;
            }
            if ($residence->statut_verification !== 'en_attente' || $residence->soumise_le === null) {
                $residence->update(['statut_verification' => 'en_attente', 'badge_verifie' => false, 'soumise_le' => now(), 'motif_refus' => null]);
                Audit::enregistrer('residence.soumise', $residence);
            }

            return false;
        });

        return redirect()->route('gerant.residences')
            ->with('message', $dejaPubliee ? 'Cette résidence est déjà validée.' : 'Votre résidence « '.$residence->nom.' » a bien été envoyée pour validation. Elle sera visible après accord d’Avelis.')
            ->with('residence_soumise', $dejaPubliee ? null : $residence->id);
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
            'profilGerant' => auth()->user()->profilGerant,
            'justificatifActuel' => $residence?->documents()->where('type', 'justificatif_droit_gestion')->whereNull('remplace_le')->latest('id')->first(),
            'residence' => $residence,
            'equipements' => Equipement::orderBy('id')->get(),
            'plancher' => PlancherAcompte::pourcentageMinimum(),
            'maxPhotos' => Photos::MAX_PAR_RESIDENCE,
            'nbPhotos' => $residence ? Photo::where('residence_id', $residence->id)->count() : 0,
        ]);
    }

    /** Nom, commune, quartier ou position GPS modifiés : la personne qui a validé la résidence doit la revoir. */
    private function identiteChange(Residence $residence, array $nouveaux): bool
    {
        if ($residence->statut_verification !== 'verifiee') {
            return false;
        }
        foreach (['nom', 'commune', 'quartier'] as $champ) {
            if ((string) $residence->{$champ} !== (string) $nouveaux[$champ]) {
                return true;
            }
        }
        foreach (['latitude', 'longitude'] as $champ) {
            if ((string) $residence->{$champ} !== (string) ($nouveaux[$champ] ?? '') && (float) $residence->{$champ} !== (float) ($nouveaux[$champ] ?? 0)) {
                return true;
            }
        }

        return false;
    }

    private function repasserEnVerification(Residence $residence): string
    {
        $residence->update(['statut_verification' => 'en_attente', 'badge_verifie' => false, 'soumise_le' => now(), 'motif_refus' => null]);
        Audit::enregistrer('residence.reverification_demandee', $residence);

        return ' Comme vous avez modifié des informations d\'identité, la résidence est repassée en vérification : elle est invisible jusqu\'à sa nouvelle validation (vos réservations déjà confirmées ne changent pas).';
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
