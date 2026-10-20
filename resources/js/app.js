// La page s'affiche d'abord (HTML + styles, environ 12 Ko) ; le reste (Livewire, Alpine, mode hors connexion, environ 100 Ko)
// se télécharge ensuite. Sur un réseau lent, c'est ce qui fait apparaître la page en moins de 3 secondes.
const demarrer = () => import('./demarrage');

if (document.readyState === 'complete') {
    demarrer();
} else {
    window.addEventListener('load', demarrer);
}
