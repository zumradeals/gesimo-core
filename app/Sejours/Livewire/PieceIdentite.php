<?php

namespace App\Sejours\Livewire;

use App\Sejours\Enums\TypePiece;
use App\Sejours\Models\PieceVoyageur;
use App\Sejours\Services\PiecesVoyageur;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Dépôt, remplacement et suppression de la pièce d'identité du voyageur.
 * Utilisé après le paiement (source « confirmation ») et dans l'espace membre (source « profil »).
 */
class PieceIdentite extends Component
{
    use WithFileUploads;

    public string $source = 'profil';

    public string $type = 'cni';

    public string $valideJusqu = '';

    public $fichier = null;

    public bool $remplacer = false;

    public ?string $message = null;

    public ?string $erreur = null;

    public function enregistrer(): void
    {
        $this->message = $this->erreur = null;
        $this->resetErrorBag();
        if (! array_key_exists($this->type, TypePiece::choix())) {
            $this->addError('type', 'Choisissez un type de pièce.');

            return;
        }
        try {
            if ($this->fichier === null) {
                throw ValidationException::withMessages(['fichier' => 'Choisissez une photo ou un fichier.']);
            }
            PiecesVoyageur::enregistrer(auth()->user(), TypePiece::from($this->type), $this->fichier, $this->valideJusqu);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $champ => $messages) {
                $this->addError($champ === 'piece' ? 'fichier' : ($champ === 'valide_jusqu' ? 'valideJusqu' : $champ), $messages[0]);
            }

            return;
        }
        $this->reset('fichier', 'valideJusqu', 'remplacer');
        $this->message = 'Votre pièce d\'identité est enregistrée. Merci !';
        $this->dispatch('piece-enregistree');
    }

    public function supprimer(): void
    {
        $this->message = $this->erreur = null;
        try {
            PiecesVoyageur::supprimer(auth()->user());
            $this->message = 'Votre pièce d\'identité est supprimée.';
        } catch (ValidationException $e) {
            $this->erreur = $e->errors()['piece'][0] ?? 'La suppression est impossible.';
        }
    }

    public function render()
    {
        $piece = PieceVoyageur::where('utilisateur_id', auth()->id())->first();

        return view('livewire.sejours.piece-identite', [
            'piece' => $piece,
            'types' => TypePiece::choix(),
            'valide' => $piece !== null && ! $piece->estExpiree(),
        ]);
    }
}
