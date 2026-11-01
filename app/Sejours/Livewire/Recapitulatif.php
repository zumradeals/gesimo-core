<?php

namespace App\Sejours\Livewire;

use App\Noyau\Services\Telephone;
use App\Paiement\Services\PaiementService;
use App\Paiement\Services\ReglagesPaiement;
use App\Sejours\Enums\HeureArriveePrevue;
use App\Sejours\Enums\MotifSejour;
use App\Sejours\Exceptions\ReservationImpossible;
use App\Sejours\Models\Unite;
use App\Sejours\Services\Disponibilites;
use App\Sejours\Services\PiecesVoyageur;
use App\Sejours\Services\ReglesSejour;
use App\Sejours\Services\ReservationService;
use App\Sejours\Services\TarifCalculateur;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Étape 1 de la réservation : « Vos informations ». Toucher « Continuer » garde les dates puis mène au paiement. */
#[Title('Vos informations')]
class Recapitulatif extends Component
{
    public Unite $unite;

    #[Url]
    public string $arrivee = '';

    #[Url]
    public string $depart = '';

    #[Url]
    public int $personnes = 1;

    public string $nom = '';

    public string $telephone = '';

    public string $motif = '';

    public string $motifPrecision = '';

    public string $heureArrivee = 'inconnue';

    public string $message = '';

    public bool $accord = false;

    public ?string $erreur = null;

    public function mount(Unite $unite): void
    {
        $unite->load('residence');
        abort_unless($unite->active && $unite->residence->estPubliee(), 404);
        $this->unite = $unite;

        // Nom et téléphone repris du profil : le voyageur les relit, il ne les retape pas.
        $this->nom = (string) auth()->user()->nom;
        $this->telephone = Telephone::afficher((string) auth()->user()->telephone);
    }

    public function garder(ReservationService $service)
    {
        $this->erreur = null;
        $this->telephone = Telephone::normaliser($this->telephone);

        $this->validate([
            'nom' => 'required|string|max:120',
            'telephone' => ['required', fn ($a, $v, $echec) => Telephone::estValide($v) ? null : $echec('Ce numéro semble incomplet : il faut 10 chiffres.')],
            'motif' => ['required', Rule::in(array_keys(MotifSejour::choix()))],
            'motifPrecision' => ['nullable', 'string', 'max:120', Rule::requiredIf($this->motif === MotifSejour::Autre->value)],
            'heureArrivee' => ['nullable', Rule::in(array_keys(HeureArriveePrevue::choix()))],
            'message' => 'nullable|string|max:500',
            'accord' => 'accepted',
        ], [
            'nom.required' => 'Indiquez votre nom comme sur votre pièce d\'identité.',
            'telephone.required' => 'Indiquez votre numéro de téléphone.',
            'motif.required' => 'Choisissez le motif de votre séjour.',
            'motif.in' => 'Choisissez le motif de votre séjour.',
            'motifPrecision.required' => 'Précisez en quelques mots le motif de votre séjour.',
            'message.max' => 'Votre message est trop long (500 caractères au maximum).',
            'accord.accepted' => 'Cochez la case pour accepter le règlement et les conditions générales.',
        ]);

        try {
            $reservation = $service->garderDates(
                auth()->user(),
                $this->unite,
                CarbonImmutable::parse($this->arrivee),
                CarbonImmutable::parse($this->depart),
                $this->personnes,
                [
                    'nom' => $this->nom,
                    'telephone' => $this->telephone,
                    'motif' => $this->motif,
                    'motif_precision' => $this->motifPrecision,
                    'heure_arrivee' => $this->heureArrivee,
                    'message' => $this->message,
                    'accord_le' => now(),
                ],
            );
        } catch (ReservationImpossible $e) {
            $this->erreur = $e->getMessage();

            return null;
        }

        if (app(PaiementService::class)->disponible()) {
            return redirect()->route('payer', $reservation);
        }

        session()->flash('message', 'Vos dates sont gardées. Elles seront libérées automatiquement si la réservation n\'est pas confirmée à temps.');

        return redirect()->route('mes-reservations');
    }

    public function render(TarifCalculateur $tarifs, Disponibilites $disponibilites, ReservationService $service)
    {
        $unite = $this->unite->load(['residence', 'tarifs']);
        $a = $this->dateOuNull($this->arrivee);
        $d = $this->dateOuNull($this->depart);
        $valide = $a && $d && $d->gt($a) && $a->gte(now()->startOfDay());
        $nuits = $valide ? (int) $a->diffInDays($d) : null;
        $horsLimites = $valide && ($nuits < ReglesSejour::nuitsMin() || $nuits > ReglesSejour::nuitsMax());

        $detail = $valide && ! $horsLimites ? $tarifs->detail($unite, $a, $d) : null;
        $total = $detail === null ? null : array_sum(array_column($detail, 'prix'));
        $libre = $detail !== null && $disponibilites->estLibre($unite, $a, $d);
        [$acompte, $solde] = $total === null ? [null, null] : $service->repartition($unite, $total);

        $limiteAnnulation = null;
        if ($valide && $unite->residence->politique_annulation === 'flexible') {
            $limiteAnnulation = ReglesSejour::limiteAnnulationGratuite($a, ReglagesPaiement::delaiAnnulationGratuiteHeures(), ReglesSejour::heureArrivee());
        }

        return view('livewire.sejours.recapitulatif', [
            'u' => $unite,
            'valide' => $valide,
            'horsLimites' => $horsLimites,
            'nuitsMin' => ReglesSejour::nuitsMin(),
            'nuitsMax' => ReglesSejour::nuitsMax(),
            'nuits' => $nuits,
            'total' => $total,
            'libre' => $libre,
            'acompte' => $acompte,
            'solde' => $solde,
            'suggestions' => $detail !== null && ! $libre ? $disponibilites->prochainesDatesLibres($unite, $a, $nuits) : [],
            'limiteAnnulation' => $limiteAnnulation,
            'heureDepart' => ReglesSejour::heureLisible(ReglesSejour::heureDepart()),
            'pieceDejaFournie' => PiecesVoyageur::pieceValide(auth()->user()) !== null,
            'dureeHeures' => ReservationService::dureeGardeMinutes() / 60,
            'dureeMinutes' => ReservationService::dureeGardeMinutes(),
            'motifs' => MotifSejour::choix(),
            'heures' => HeureArriveePrevue::choix(),
        ]);
    }

    private function dateOuNull(string $valeur): ?CarbonImmutable
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $valeur)) {
            return null;
        }
        try {
            return CarbonImmutable::createFromFormat('!Y-m-d', $valeur) ?: null;
        } catch (\Throwable) {
            return null;
        }
    }
}
