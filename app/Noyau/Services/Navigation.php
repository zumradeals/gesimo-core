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
        $chercher = ['libelle' => 'Chercher', 'icone' => 'recherche', 'route' => 'recherche', 'actif' => ['accueil', 'recherche', 'logement', 'reserver']];

        $aide = ['libelle' => 'Aide', 'icone' => 'aide', 'route' => 'faq', 'actif' => ['faq', 'contact', 'a-propos', 'conditions', 'confidentialite', 'devenir-partenaire']];

        if ($u === null) {
            return [$chercher, $aide, ['libelle' => 'Se connecter', 'icone' => 'compte', 'route' => 'connexion', 'actif' => ['connexion', 'inscription', 'connexion.lien']]];
        }

        $menu = [];
        if ($u->estAdmin()) {
            $menu[] = ['libelle' => 'Accueil', 'icone' => 'maison', 'route' => 'admin.tableau', 'actif' => ['admin.tableau', 'admin.reservations', 'admin.litiges', 'admin.journal', 'admin.messages', 'admin.mises-en-avant', 'admin.residences', 'admin.residence']];
            $menu[] = ['libelle' => 'Paiements', 'icone' => 'carte', 'route' => 'admin.paiements', 'actif' => ['admin.paiements']];
            $menu[] = ['libelle' => 'Reversements', 'icone' => 'argent', 'route' => 'admin.reversements', 'actif' => ['admin.reversements']];
            $menu[] = ['libelle' => 'Contrôle', 'icone' => 'balance', 'route' => 'admin.controle', 'actif' => ['admin.controle']];
            if ($u->estSuperAdmin()) {
                // Six onglets sur un téléphone de 360 px : « Reversements » devient « Verser » pour tenir.
                $menu[2]['libelle'] = 'Verser';
                $menu[] = ['libelle' => 'Réglages', 'icone' => 'cle', 'route' => 'admin.reglages', 'actif' => ['admin.reglages']];
            }
        }
        if ($u->aRole(Role::Gerant)) {
            $menu[] = ['libelle' => 'Arrivées', 'icone' => 'porte', 'route' => 'gerant.arrivees', 'actif' => ['gerant.arrivees']];
            $menu[] = ['libelle' => 'Calendrier', 'icone' => 'calendrier', 'route' => 'gerant.calendrier', 'actif' => ['gerant.calendrier', 'gerant.telephone', 'gerant.reservations', 'gerant.probleme']];
            $menu[] = ['libelle' => 'Argent', 'icone' => 'argent', 'route' => 'gerant.argent', 'actif' => ['gerant.argent']];
            $menu[] = ['libelle' => 'Résidences', 'icone' => 'batiment', 'route' => 'gerant.residences', 'actif' => ['gerant.residences', 'gerant.residence.nouvelle', 'gerant.residence', 'gerant.personnel', 'gerant.aide', 'gerant.mises-en-avant', 'gerant.mise-en-avant.acheter', 'gerant.mise-en-avant.retour']];
        } elseif ($u->aRole(Role::Personnel)) {
            $menu[] = ['libelle' => 'Arrivées', 'icone' => 'porte', 'route' => 'gerant.arrivees', 'actif' => ['gerant.arrivees']];
            $menu[] = ['libelle' => 'Calendrier', 'icone' => 'calendrier', 'route' => 'gerant.calendrier', 'actif' => ['gerant.calendrier']];
        }
        if ($u->aRole(Role::Client)) {
            $menu[] = $chercher;
            $menu[] = ['libelle' => 'Mes réservations', 'icone' => 'liste', 'route' => 'mes-reservations', 'actif' => ['mes-reservations', 'payer', 'paiement.retour', 'reservation.confirmation', 'reservation.annuler', 'reservation.detail', 'reservation.avis', 'reservation.probleme']];
            $menu[] = $aide;
        }
        $menu[] = ['libelle' => 'Compte', 'icone' => 'compte', 'route' => 'compte', 'actif' => ['compte', 'profil']];

        return $menu;
    }

    /** Pages publiques qui occupent toute la largeur (elles dessinent elles-mêmes leurs sections). */
    public const PAGES_PLEINES = ['accueil', 'recherche', 'logement', 'a-propos', 'devenir-partenaire', 'faq', 'contact', 'conditions', 'confidentialite', 'mentions-legales', 'connexion', 'inscription', 'connexion.lien', 'invitation'];

    /** Pages d'un compte (client, gérant) qui gardent le site public autour d'elles. */
    public const PAGES_PUBLIQUES = ['accueil', 'recherche', 'logement', 'a-propos', 'devenir-partenaire', 'faq', 'contact', 'conditions', 'confidentialite', 'mentions-legales', 'connexion', 'inscription', 'connexion.lien', 'invitation'];

    /**
     * Habillage de la page : « site » (visiteur et client), « admin » (menu latéral) ou « app » (gérant et personnel).
     */
    public static function habillage(?Utilisateur $u, ?string $route): string
    {
        $route ??= '';
        if ($u !== null && $u->estAdmin() && (str_starts_with($route, 'admin.') || in_array($route, ['compte', 'profil'], true))) {
            return 'admin';
        }
        if ($u !== null && ($u->estAdmin() || $u->aRole(Role::Gerant) || $u->aRole(Role::Personnel)) && ! in_array($route, self::PAGES_PUBLIQUES, true)) {
            return 'app';
        }

        return 'site';
    }

    /** Menu de l'en-tête du site public. @return list<array{libelle: string, route: string, actif: list<string>}> */
    public static function site(): array
    {
        return [
            ['libelle' => 'Accueil', 'route' => 'accueil', 'actif' => ['accueil']],
            ['libelle' => 'Logements', 'route' => 'recherche', 'actif' => ['recherche', 'logement']],
            ['libelle' => 'À propos', 'route' => 'a-propos', 'actif' => ['a-propos']],
            ['libelle' => 'Devenir partenaire', 'route' => 'devenir-partenaire', 'actif' => ['devenir-partenaire']],
            ['libelle' => 'Aide', 'route' => 'faq', 'actif' => ['faq']],
            ['libelle' => 'Contact', 'route' => 'contact', 'actif' => ['contact']],
        ];
    }

    /**
     * Menu latéral de l'administration.
     *
     * @return list<array{libelle: string, icone: string, route: string, actif: list<string>}>
     */
    public static function admin(Utilisateur $u): array
    {
        $menu = [
            ['libelle' => 'Tableau de bord', 'icone' => 'maison', 'route' => 'admin.tableau', 'actif' => ['admin.tableau']],
            ['libelle' => 'Résidences à valider', 'icone' => 'batiment', 'route' => 'admin.residences', 'actif' => ['admin.residences', 'admin.residence']],
            ['libelle' => 'Réservations', 'icone' => 'liste', 'route' => 'admin.reservations', 'actif' => ['admin.reservations']],
            ['libelle' => 'Litiges', 'icone' => 'alerte', 'route' => 'admin.litiges', 'actif' => ['admin.litiges']],
            ['libelle' => 'Paiements', 'icone' => 'carte', 'route' => 'admin.paiements', 'actif' => ['admin.paiements']],
            ['libelle' => 'Reversements', 'icone' => 'argent', 'route' => 'admin.reversements', 'actif' => ['admin.reversements']],
            ['libelle' => 'Contrôle', 'icone' => 'balance', 'route' => 'admin.controle', 'actif' => ['admin.controle']],
            ['libelle' => 'Mises en avant', 'icone' => 'etoile', 'route' => 'admin.mises-en-avant', 'actif' => ['admin.mises-en-avant']],
            ['libelle' => 'Journal d\'audit', 'icone' => 'equipe', 'route' => 'admin.journal', 'actif' => ['admin.journal']],
        ];
        if ($u->estSuperAdmin()) {
            $menu[] = ['libelle' => 'Modèles d\'e-mails', 'icone' => 'mail', 'route' => 'admin.messages', 'actif' => ['admin.messages']];
            $menu[] = ['libelle' => 'Réglages', 'icone' => 'reglage', 'route' => 'admin.reglages', 'actif' => ['admin.reglages']];
        }

        return $menu;
    }

    /** Les cinq entrées du menu du bas (téléphone) ; le reste est derrière « Plus ». @return list<string> routes */
    public const ADMIN_BAS = ['admin.tableau', 'admin.reservations', 'admin.paiements', 'admin.controle'];
}
