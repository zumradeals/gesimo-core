<?php

namespace App\Noyau\Services;

use App\Noyau\Enums\Role;
use App\Noyau\Models\Utilisateur;

/** Menus selon le rôle : barre du haut sur ordinateur, barre d'onglets en bas sur téléphone. */
class Navigation
{
    /** @return list<array{libelle: string, icone: string, route: string, actif: list<string>}> */
    public static function menu(?Utilisateur $u): array
    {
        $chercher = ['libelle' => 'Chercher', 'icone' => 'recherche', 'route' => 'accueil', 'actif' => ['accueil', 'recherche', 'logement', 'reserver']];

        $aide = ['libelle' => 'Aide', 'icone' => 'aide', 'route' => 'faq', 'actif' => ['faq', 'contact', 'a-propos', 'conditions', 'confidentialite', 'devenir-partenaire']];

        if ($u === null) {
            return [$chercher, $aide, ['libelle' => 'Se connecter', 'icone' => 'compte', 'route' => 'connexion', 'actif' => ['connexion', 'inscription', 'connexion.lien']]];
        }

        $menu = [];
        if ($u->estAdmin()) {
            $menu[] = ['libelle' => 'Accueil', 'icone' => 'maison', 'route' => 'admin.tableau', 'actif' => ['admin.tableau', 'admin.reservations', 'admin.litiges', 'admin.journal', 'admin.messages', 'admin.residences', 'admin.residence']];
            $menu[] = ['libelle' => 'Paiements', 'icone' => 'carte', 'route' => 'admin.paiements', 'actif' => ['admin.paiements']];
            $menu[] = ['libelle' => 'Reversements', 'icone' => 'argent', 'route' => 'admin.reversements', 'actif' => ['admin.reversements']];
            $menu[] = ['libelle' => 'Contrôle', 'icone' => 'balance', 'route' => 'admin.controle', 'actif' => ['admin.controle']];
        }
        if ($u->aRole(Role::Gerant)) {
            $menu[] = ['libelle' => 'Arrivées', 'icone' => 'porte', 'route' => 'gerant.arrivees', 'actif' => ['gerant.arrivees']];
            $menu[] = ['libelle' => 'Calendrier', 'icone' => 'calendrier', 'route' => 'gerant.calendrier', 'actif' => ['gerant.calendrier', 'gerant.telephone', 'gerant.reservations', 'gerant.probleme']];
            $menu[] = ['libelle' => 'Argent', 'icone' => 'argent', 'route' => 'gerant.argent', 'actif' => ['gerant.argent']];
            $menu[] = ['libelle' => 'Résidences', 'icone' => 'batiment', 'route' => 'gerant.residences', 'actif' => ['gerant.residences', 'gerant.residence.nouvelle', 'gerant.residence', 'gerant.personnel', 'gerant.aide']];
        } elseif ($u->aRole(Role::Personnel)) {
            $menu[] = ['libelle' => 'Arrivées', 'icone' => 'porte', 'route' => 'gerant.arrivees', 'actif' => ['gerant.arrivees']];
            $menu[] = ['libelle' => 'Calendrier', 'icone' => 'calendrier', 'route' => 'gerant.calendrier', 'actif' => ['gerant.calendrier']];
        }
        if ($u->aRole(Role::Client)) {
            $menu[] = $chercher;
            $menu[] = ['libelle' => 'Mes réservations', 'icone' => 'liste', 'route' => 'mes-reservations', 'actif' => ['mes-reservations', 'payer', 'paiement.retour', 'reservation.confirmation', 'reservation.annuler', 'reservation.detail', 'reservation.avis', 'reservation.probleme']];
            $menu[] = $aide;
        }
        $menu[] = ['libelle' => 'Mon compte', 'icone' => 'compte', 'route' => 'compte', 'actif' => ['compte', 'profil']];

        return $menu;
    }
}
