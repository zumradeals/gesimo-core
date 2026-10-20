<?php

namespace App\Noyau\Livewire\Admin;

use App\Noyau\Models\JournalAudit as Journal;
use App\Noyau\Models\Utilisateur;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** F-A5 : qui a fait quoi, quand. Lecture seule ; les détails ne contiennent jamais de secret. */
#[Title('Journal d\'audit')]
class JournalAudit extends Component
{
    use WithPagination;

    #[Url]
    public string $filtre = '';

    public const FILTRES = [
        '' => 'Tout',
        'compte' => 'Comptes',
        'reservation' => 'Réservations',
        'reversement' => 'Argent',
        'litige' => 'Litiges',
        'residence' => 'Résidences',
        'parametre' => 'Réglages',
    ];

    public function mount(): void
    {
        abort_unless(Gate::allows('voir-journal-audit'), 403);
    }

    public function updatedFiltre(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $filtre = array_key_exists($this->filtre, self::FILTRES) ? $this->filtre : '';

        return view('livewire.admin.journal-audit', [
            'lignes' => Journal::query()->when($filtre !== '', fn ($q) => $q->where('action', 'like', $filtre.'.%'))->latest('id')->paginate(30),
            'noms' => Utilisateur::whereIn('id', Journal::query()->latest('id')->limit(300)->pluck('utilisateur_id')->filter())->pluck('nom', 'id'),
            'filtres' => self::FILTRES,
        ]);
    }
}
