// Livewire (et Alpine, qu'il embarque) et les fonctions « application » : chargés après l'affichage de la page.
import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';
import './pwa';
import carrouselCommunes from './carrousel-communes';

Alpine.data('carrouselCommunes', carrouselCommunes);

Livewire.start();
