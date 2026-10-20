<?php

namespace App\Noyau\Livewire;

use App\Parametres\Services\Parametres;
use Livewire\Component;

/** Pages d'information (à propos, FAQ, conditions…). Une seule classe, un texte par page dans resources/views/pages. */
class PageStatique extends Component
{
    public const PAGES = [
        'a-propos' => 'À propos',
        'faq' => 'Questions fréquentes',
        'conditions' => 'Conditions générales',
        'confidentialite' => 'Confidentialité',
        'devenir-partenaire' => 'Devenir gérant partenaire',
        'aide-gerant' => 'Aide du gérant',
    ];

    public string $page = 'faq';

    public function mount(string $page): void
    {
        abort_unless(isset(self::PAGES[$page]), 404);
        $this->page = $page;
    }

    /** Pages dont le texte peut être remplacé dans les Paramètres (Général). */
    private const REGLAGES = ['a-propos' => 'general.a_propos', 'conditions' => 'general.conditions', 'confidentialite' => 'general.confidentialite'];

    public function render()
    {
        $texte = isset(self::REGLAGES[$this->page]) ? trim((string) Parametres::obtenir(self::REGLAGES[$this->page])) : '';

        return view('livewire.noyau.page-statique', ['texte' => $texte !== '' ? $texte : null])->title(self::PAGES[$this->page]);
    }
}
