<?php

/*
 * Livewire est compilé avec le reste de l'application (resources/js/app.js) au lieu d'être ajouté par un script séparé :
 * il ne bloque plus l'affichage de la page, il est mis en cache avec un nom qui change à chaque version, et c'est un
 * fichier de moins à télécharger sur un réseau lent. Les autres réglages restent ceux de Livewire.
 */
return [
    'inject_assets' => false,
];
