<?php

namespace App\Noyau\Livewire;

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

    public function render()
    {
        return view('livewire.noyau.page-statique')->title(self::PAGES[$this->page]);
    }
}
