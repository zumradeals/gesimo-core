<?php

namespace App\Noyau\Livewire;

use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Mon compte')]
class Compte extends Component
{
    public function render()
    {
        return view('livewire.noyau.compte', ['utilisateur' => auth()->user()->load('roles')]);
    }
}
