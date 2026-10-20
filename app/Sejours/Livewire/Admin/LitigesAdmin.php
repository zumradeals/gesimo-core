<?php

namespace App\Sejours\Livewire\Admin;

use App\Sejours\Exceptions\ReservationImpossible;
use App\Sejours\Models\Litige;
use App\Sejours\Services\LitigeService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;

/** F-A5 : l'administrateur traite les litiges. Aucun mouvement d'argent n'est fait depuis cet écran. */
#[Title('Litiges')]
class LitigesAdmin extends Component
{
    /** @var array<int, string> */
    public array $decisions = [];

    public ?string $message = null;

    public ?string $erreur = null;

    public function mount(): void
    {
        abort_unless(Gate::allows('traiter-litiges'), 403);
    }

    public function clore(int $id, LitigeService $service): void
    {
        $this->message = $this->erreur = null;
        try {
            $service->clore(Litige::findOrFail($id), auth()->user(), $this->decisions[$id] ?? '');
            unset($this->decisions[$id]);
            $this->message = 'Litige clos. Les deux parties sont prévenues par e-mail.';
        } catch (ReservationImpossible $e) {
            $this->erreur = $e->getMessage();
        }
    }

    public function render()
    {
        $avec = ['reservation.unite.residence', 'auteur', 'partieAdverse'];

        return view('livewire.admin.litiges-admin', [
            'ouverts' => Litige::where('statut', 'ouvert')->with($avec)->orderBy('id')->get(),
            'clos' => Litige::where('statut', 'resolu')->with($avec)->latest('decide_le')->limit(20)->get(),
        ]);
    }
}
