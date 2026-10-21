<?php

namespace App\Parametres\Services;

use App\Noyau\Models\Utilisateur;
use App\Noyau\Services\Sms\FournisseurSms;
use App\Paiement\Services\PlancherAcompte;
use App\Parametres\Exceptions\ReglageInvalide;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Catalogue de tous les réglages de la plateforme (RG-20) : un seul endroit dit, pour chaque réglage, sa rubrique,
 * son type, ses limites, s'il est secret et s'il est sensible (mot de passe à ressaisir, RG-22).
 * L'écran Paramètres et l'assistant d'installation sont construits à partir de ce catalogue.
 */
class Reglages
{
    public const RUBRIQUES = [
        'general' => 'Général',
        'reservation' => 'Réservation',
        'paiement' => 'Paiement',
        'notifications' => 'Notifications',
        'mise_en_avant' => 'Mise en avant',
        'securite' => 'Sécurité',
    ];

    /**
     * Types : texte, long, url, entier, decimal, heure, choix, secret, logo, image, offres.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function definitions(): array
    {
        $d = [];
        $ajouter = function (string $cle, string $rubrique, string $label, string $type, array $plus = []) use (&$d) {
            $d[$cle] = $plus + ['cle' => $cle, 'rubrique' => $rubrique, 'label' => $label, 'type' => $type, 'defaut' => null, 'sensible' => false, 'aide' => null];
        };

        // Général
        $ajouter('general.nom', 'general', 'Nom de la plateforme', 'texte', ['defaut' => 'GESIMO Séjours', 'max' => 80, 'section' => 'Identité']);
        $ajouter('general.slogan', 'general', 'Slogan', 'texte', ['max' => 120, 'section' => 'Identité', 'aide' => 'Phrase courte affichée sous le titre de la page d\'accueil et dans le pied de page.']);
        $ajouter('general.logo', 'general', 'Logo', 'logo', ['section' => 'Identité', 'aide' => 'Image PNG ou JPG, 1 Mo maximum.']);
        $ajouter('general.image_accueil', 'general', 'Image de la page d\'accueil', 'image', ['section' => 'Identité', 'aide' => 'Photo large (PNG ou JPG, 4 Mo maximum) derrière le titre de l\'accueil. Vide : fond vert.']);
        $ajouter('contact.email', 'general', 'E-mail de contact', 'texte', ['max' => 190, 'section' => 'Coordonnées', 'aide' => 'Les messages du formulaire « Contact » arrivent ici.']);
        $ajouter('general.telephone', 'general', 'Téléphone de contact', 'texte', ['max' => 30, 'section' => 'Coordonnées']);
        $ajouter('general.whatsapp', 'general', 'Numéro WhatsApp', 'texte', ['max' => 30, 'section' => 'Coordonnées', 'aide' => 'Avec l\'indicatif, par exemple +225 07 00 00 00 00. Vide : aucun lien WhatsApp.']);
        $ajouter('general.adresse', 'general', 'Adresse', 'texte', ['max' => 190, 'section' => 'Coordonnées']);
        $ajouter('general.horaires', 'general', 'Horaires du service client', 'texte', ['max' => 120, 'section' => 'Coordonnées', 'aide' => 'Par exemple : Lundi au samedi, 8 h – 18 h.']);
        foreach (['facebook' => 'Facebook', 'instagram' => 'Instagram', 'linkedin' => 'LinkedIn', 'tiktok' => 'TikTok'] as $reseau => $nom) {
            $ajouter("social.{$reseau}", 'general', "Page {$nom}", 'url', ['max' => 200, 'section' => 'Réseaux sociaux', 'aide' => $reseau === 'facebook' ? 'Adresse complète commençant par https://. Vide : l\'icône n\'est pas affichée.' : null]);
        }
        $ajouter('general.raison_sociale', 'general', 'Raison sociale', 'texte', ['max' => 120, 'section' => 'Informations légales']);
        $ajouter('general.rccm', 'general', 'Numéro RCCM', 'texte', ['max' => 60, 'section' => 'Informations légales']);
        $ajouter('general.nif', 'general', 'Numéro de compte contribuable (NIF)', 'texte', ['max' => 60, 'section' => 'Informations légales']);
        $ajouter('general.a_propos', 'general', 'À propos', 'long', ['max' => 20000, 'section' => 'Textes des pages', 'aide' => 'Affiché sur la page « À propos ». Vide : le texte provisoire du site reste affiché.']);
        $ajouter('general.conditions', 'general', 'Conditions générales', 'long', ['max' => 40000, 'section' => 'Textes des pages', 'aide' => 'Remplace le brouillon du site dès qu\'il est rempli.']);
        $ajouter('general.confidentialite', 'general', 'Politique de confidentialité', 'long', ['max' => 40000, 'section' => 'Textes des pages', 'aide' => 'Vide : le brouillon du site reste affiché.']);
        $ajouter('general.mentions_legales', 'general', 'Mentions légales', 'long', ['max' => 20000, 'section' => 'Textes des pages', 'aide' => 'Vide : la page affiche automatiquement la raison sociale, le RCCM, le NIF et l\'adresse ci-dessus.']);

        // Réservation
        $ajouter('reservation.duree_blocage_minutes', 'reservation', 'Durée du blocage des dates (minutes)', 'entier', ['defaut' => '120', 'min' => 15, 'max' => 1440, 'aide' => 'Défaut 120. Les réservations déjà en attente gardent leur durée.']);
        $ajouter('reservation.acompte_defaut_pourcentage', 'reservation', 'Acompte par défaut (%)', 'entier', ['defaut' => '30', 'min' => 1, 'max' => 100, 'aide' => 'Proposé aux nouvelles résidences ; jamais sous le plancher.']);
        $ajouter('annulation.politique_defaut', 'reservation', 'Politique d\'annulation par défaut', 'choix', ['defaut' => 'flexible', 'choix' => ['flexible' => 'Flexible (remboursement intégral jusqu\'au délai ci-dessous)'], 'aide' => 'D\'autres politiques seront ajoutées plus tard.']);
        $ajouter('annulation.delai_gratuit_heures', 'reservation', 'Délai d\'annulation gratuite (heures avant l\'arrivée)', 'entier', ['defaut' => '24', 'min' => 0, 'max' => 720, 'aide' => 'Les réservations déjà faites gardent leur délai.']);
        $ajouter('annulation.heure_arrivee', 'reservation', 'Heure d\'arrivée supposée', 'heure', ['defaut' => '14:00', 'aide' => 'Sert à calculer le délai ci-dessus.']);

        // Paiement (sensible)
        $ajouter('geniuspay.mode', 'paiement', 'Mode', 'choix', ['defaut' => 'sandbox', 'sensible' => true, 'choix' => ['sandbox' => 'Test (aucun vrai argent)', 'live' => 'Réel'], 'aide' => 'Le passage en « Réel » demande que votre compte GeniusPay soit vérifié et les clés réelles saisies.']);
        foreach (['sandbox' => 'test', 'live' => 'réel'] as $mode => $nom) {
            $ajouter("geniuspay.{$mode}.cle_publique", 'paiement', "Clé publique ({$nom})", 'texte', ['sensible' => true, 'max' => 200, 'groupe' => $mode]);
            $ajouter("geniuspay.{$mode}.cle_secrete", 'paiement', "Clé secrète ({$nom})", 'secret', ['sensible' => true, 'groupe' => $mode]);
            $ajouter("geniuspay.{$mode}.webhook_secret", 'paiement', "Secret du webhook ({$nom})", 'secret', ['sensible' => true, 'groupe' => $mode]);
        }
        $ajouter('commission.pourcentage', 'paiement', 'Taux de commission (%)', 'decimal', ['sensible' => true, 'min' => 0, 'max' => 100, 'aide' => 'Appliqué aux nouvelles réservations, figé à la création de chacune.']);
        $ajouter('paiement.frais_pourcentage', 'paiement', 'Frais GeniusPay estimés (%)', 'decimal', ['sensible' => true, 'min' => 0, 'max' => 100, 'aide' => 'Sert à calculer l\'acompte minimum.']);

        // Notifications
        $ajouter('email.hote', 'notifications', 'Serveur d\'e-mail (hôte)', 'texte', ['max' => 190]);
        $ajouter('email.port', 'notifications', 'Port', 'entier', ['defaut' => '587', 'min' => 1, 'max' => 65535]);
        $ajouter('email.chiffrement', 'notifications', 'Chiffrement', 'choix', ['defaut' => 'tls', 'choix' => ['tls' => 'TLS (port 587)', 'ssl' => 'SSL (port 465)']]);
        $ajouter('email.utilisateur', 'notifications', 'Utilisateur', 'texte', ['max' => 190]);
        $ajouter('email.mot_de_passe', 'notifications', 'Mot de passe du serveur d\'e-mail', 'secret');
        $ajouter('email.expediteur_nom', 'notifications', 'Nom de l\'expéditeur', 'texte', ['defaut' => 'GESIMO Séjours', 'max' => 80]);
        $ajouter('email.expediteur_adresse', 'notifications', 'Adresse de l\'expéditeur', 'texte', ['max' => 190]);
        $ajouter('sms.actif', 'notifications', 'Connexion par code SMS', 'choix', ['defaut' => '0', 'choix' => ['0' => 'Désactivée (par défaut)', '1' => 'Activée'], 'aide' => 'Désactivée : connexion par mot de passe ou lien e-mail. L\'activation demande un fournisseur SMS (pas encore branché).']);

        // Mise en avant
        $ajouter('mise_en_avant.offres', 'mise_en_avant', 'Offres', 'offres', ['aide' => 'Chaque offre : un nom, une durée et un tarif. Les mises en avant déjà achetées gardent leur prix.']);

        // Sécurité (sensible)
        $ajouter('securite.session_minutes', 'securite', 'Durée de session (minutes)', 'entier', ['defaut' => '120', 'min' => 15, 'max' => 1440, 'sensible' => true, 'aide' => 'Au bout de ce temps sans activité, il faut se reconnecter.']);
        $ajouter('securite.connexion_essais_max', 'securite', 'Tentatives de connexion autorisées', 'entier', ['defaut' => '5', 'min' => 3, 'max' => 10, 'sensible' => true, 'aide' => 'Après ce nombre d\'échecs, une pause est imposée.']);
        $ajouter('securite.connexion_pause_minutes', 'securite', 'Durée de la pause (minutes)', 'entier', ['defaut' => '5', 'min' => 1, 'max' => 120, 'sensible' => true]);

        return $d;
    }

    /** @return array<string, array<string, mixed>> */
    public static function pourRubrique(string $rubrique): array
    {
        return array_filter(self::definitions(), fn ($r) => $r['rubrique'] === $rubrique);
    }

    /** Valeur courante d'un réglage non secret, avec son défaut. */
    public static function valeur(string $cle): ?string
    {
        $def = self::definitions()[$cle] ?? null;

        return Parametres::obtenir($cle, $def['defaut'] ?? null);
    }

    public static function entier(string $cle): int
    {
        $def = self::definitions()[$cle];
        $v = Parametres::obtenir($cle);

        // À la lecture on refuse seulement l'impossible (négatif, ou nul quand zéro n'a pas de sens) ;
        // les limites « confortables » sont imposées à l'enregistrement dans l'écran Paramètres.
        return is_numeric($v) && (int) $v >= min(1, $def['min'] ?? 0) ? (int) $v : (int) $def['defaut'];
    }

    /**
     * Enregistre les valeurs d'une rubrique. Tout est contrôlé AVANT d'écrire quoi que ce soit : une valeur refusée
     * ne laisse aucun changement partiel. Les secrets laissés vides restent inchangés.
     *
     * @param  array<string, mixed>  $valeurs  réglages non secrets (clé => valeur saisie)
     * @param  array<string, string>  $secrets  nouveaux secrets saisis (clé => valeur)
     * @return list<string> clés réellement modifiées
     *
     * @throws ReglageInvalide
     */
    public static function enregistrer(string $rubrique, array $valeurs, array $secrets, Utilisateur $auteur, ?string $motDePasse, bool $installation = false): array
    {
        $defs = self::pourRubrique($rubrique);
        $erreurs = [];
        $changes = []; // cle => [valeurNormalisee, estSecret]

        foreach ($defs as $cle => $def) {
            if (in_array($def['type'], ['logo', 'image'], true)) {
                continue; // géré par l'écran (téléversement)
            }
            if ($def['type'] === 'secret') {
                $nouveau = trim((string) ($secrets[$cle] ?? ''));
                if ($nouveau !== '') {
                    $changes[$cle] = [$nouveau, true];
                }

                continue;
            }
            if (! array_key_exists($cle, $valeurs)) {
                continue;
            }
            try {
                $normalisee = self::normaliser($def, $valeurs[$cle]);
            } catch (ReglageInvalide $e) {
                $erreurs[$cle] = $e->getMessage();

                continue;
            }
            $actuelle = Parametres::obtenir($cle) ?? '';
            if ($normalisee !== $actuelle && ! ($normalisee === ($def['defaut'] ?? '') && $actuelle === '')) {
                $changes[$cle] = [$normalisee, false];
            }
        }

        $erreurs += self::controlesCroises($rubrique, $changes);
        if ($erreurs !== []) {
            throw new ReglageInvalide($erreurs);
        }
        if ($changes === []) {
            return [];
        }

        $sensible = array_filter(array_keys($changes), fn ($c) => $defs[$c]['sensible']);
        if ($sensible !== [] && ! $installation) {
            self::verifierMotDePasse($auteur, $motDePasse);
        }

        DB::transaction(function () use ($changes, $auteur, $rubrique) {
            foreach ($changes as $cle => [$valeur, $secret]) {
                Parametres::definir($cle, $valeur === '' ? null : $valeur, $auteur, $secret, $rubrique);
            }
        });

        return array_keys($changes);
    }

    /** Valeur propre à enregistrer, ou refus avec un message clair. */
    private static function normaliser(array $def, mixed $brut): string
    {
        $v = is_string($brut) ? trim($brut) : (is_scalar($brut) ? trim((string) $brut) : '');
        $label = $def['label'];
        $nom = mb_strtolower(preg_replace('/\s*\(.*\)$/u', '', $label));

        switch ($def['type']) {
            case 'texte':
            case 'long':
            case 'url':
                if ($def['type'] === 'url' && $v !== '' && (! preg_match('#^https://[^\s/]+\.[^\s/]+(/\S*)?$#i', $v) || filter_var($v, FILTER_VALIDATE_URL) === false)) {
                    throw new ReglageInvalide([$def['cle'] => 'Indiquez une adresse complète commençant par https:// (exemple : https://www.facebook.com/gesimo).']);
                }
                if ($v === '' && ($def['cle'] === 'general.nom')) {
                    throw new ReglageInvalide([$def['cle'] => 'Le nom de la plateforme ne peut pas être vide.']);
                }
                if (isset($def['max']) && mb_strlen($v) > $def['max']) {
                    throw new ReglageInvalide([$def['cle'] => "{$label} : {$def['max']} caractères au maximum."]);
                }
                if (in_array($def['cle'], ['contact.email', 'email.expediteur_adresse'], true) && $v !== '' && filter_var($v, FILTER_VALIDATE_EMAIL) === false) {
                    throw new ReglageInvalide([$def['cle'] => 'Cette adresse e-mail n\'est pas valide.']);
                }
                if ($def['cle'] === 'email.hote' && $v !== '' && ! preg_match('/^[a-z0-9]([a-z0-9.\-]*[a-z0-9])?$/i', $v)) {
                    throw new ReglageInvalide([$def['cle'] => 'Le nom du serveur n\'est pas valide (exemple : smtp.exemple.ci).']);
                }
                if (in_array($def['cle'], ['general.telephone', 'general.whatsapp'], true) && $v !== '' && ! preg_match('/^[0-9 +().\-]{6,30}$/', $v)) {
                    throw new ReglageInvalide([$def['cle'] => 'Le numéro de téléphone n\'est pas valide.']);
                }

                return $v;
            case 'entier':
                if (! preg_match('/^\d+$/', $v)) {
                    throw new ReglageInvalide([$def['cle'] => "{$label} : entrez un nombre entier."]);
                }
                if ((int) $v < $def['min'] || (int) $v > $def['max']) {
                    throw new ReglageInvalide([$def['cle'] => "{$label} : entre {$def['min']} et {$def['max']}."]);
                }

                return (string) (int) $v;
            case 'decimal':
                $v = str_replace(',', '.', $v);
                if ($v === '') {
                    return '';
                }
                if (! is_numeric($v) || (float) $v < $def['min'] || (float) $v > $def['max']) {
                    throw new ReglageInvalide([$def['cle'] => "{$label} : entrez un pourcentage entre {$def['min']} et {$def['max']} (pas de valeur négative)."]);
                }

                return rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.') ?: '0';
            case 'heure':
                if (! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $v)) {
                    throw new ReglageInvalide([$def['cle'] => 'Indiquez une heure au format 14:00.']);
                }

                return $v;
            case 'choix':
                if (! array_key_exists($v, $def['choix'])) {
                    throw new ReglageInvalide([$def['cle'] => 'Ce choix n\'existe pas.']);
                }

                return $v;
            case 'offres':
                return self::normaliserOffres($brut);
        }

        return $v;
    }

    /** @return string JSON des offres, ou refus. */
    private static function normaliserOffres(mixed $brut): string
    {
        $liste = is_array($brut) ? array_values($brut) : [];
        if (count($liste) > 4) {
            throw new ReglageInvalide(['mise_en_avant.offres' => '4 offres au maximum.']);
        }
        $propres = [];
        foreach ($liste as $i => $o) {
            $n = $i + 1;
            $nom = trim((string) ($o['nom'] ?? ''));
            $jours = preg_replace('/\s+/', '', (string) ($o['jours'] ?? ''));
            $prix = preg_replace('/[\s\x{202F}\x{00A0}]+/u', '', (string) ($o['prix'] ?? ''));
            if ($nom === '' || mb_strlen($nom) > 60) {
                throw new ReglageInvalide(['mise_en_avant.offres' => "Offre {$n} : indiquez un nom (60 caractères au maximum)."]);
            }
            if (! preg_match('/^\d+$/', $jours) || (int) $jours < 1 || (int) $jours > 365) {
                throw new ReglageInvalide(['mise_en_avant.offres' => "Offre {$n} : la durée doit être un nombre de jours entre 1 et 365."]);
            }
            if (! preg_match('/^\d+$/', $prix) || (int) $prix < 200) {
                throw new ReglageInvalide(['mise_en_avant.offres' => "Offre {$n} : le prix doit être un nombre entier d'au moins 200 FCFA (minimum de GeniusPay)."]);
            }
            $propres[] = ['nom' => $nom, 'jours' => (int) $jours, 'prix' => (int) $prix];
        }

        return json_encode($propres, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Contrôles qui dépendent de plusieurs réglages à la fois.
     *
     * @param  array<string, array{0: string, 1: bool}>  $changes
     * @return array<string, string>
     */
    private static function controlesCroises(string $rubrique, array $changes): array
    {
        $erreurs = [];
        $val = fn (string $cle) => array_key_exists($cle, $changes) ? $changes[$cle][0] : Parametres::obtenir($cle);

        if ($rubrique === 'paiement') {
            $commission = $val('commission.pourcentage');
            $frais = $val('paiement.frais_pourcentage');
            $acompte = (int) ($val('reservation.acompte_defaut_pourcentage') ?: 0);
            $plancher = PlancherAcompte::calculer($commission, $frais);
            if ($plancher !== null && $acompte > 0 && $acompte < $plancher && array_key_exists('commission.pourcentage', $changes)) {
                $erreurs['commission.pourcentage'] = "Avec ce taux, l'acompte minimum devient {$plancher} % : l'acompte par défaut ({$acompte} %) est trop bas. Augmentez-le d'abord dans « Réservation ».";
            }
            $mode = $val('geniuspay.mode') ?: 'sandbox';
            if ($mode === 'live') {
                foreach (['cle_publique', 'cle_secrete', 'webhook_secret'] as $c) {
                    $cle = "geniuspay.live.{$c}";
                    if (! array_key_exists($cle, $changes) && ! Parametres::estDefini($cle)) {
                        $erreurs['geniuspay.mode'] = 'Le mode « Réel » demande les trois clés réelles : saisissez-les d\'abord.';
                        break;
                    }
                }
            }
        }

        if ($rubrique === 'reservation' && array_key_exists('reservation.acompte_defaut_pourcentage', $changes)) {
            $plancher = PlancherAcompte::pourcentageMinimum();
            if ($plancher !== null && (int) $changes['reservation.acompte_defaut_pourcentage'][0] < $plancher) {
                $erreurs['reservation.acompte_defaut_pourcentage'] = "L'acompte par défaut ne peut pas être inférieur à {$plancher} % (commission + frais).";
            }
        }

        if ($rubrique === 'notifications' && array_key_exists('sms.actif', $changes) && $changes['sms.actif'][0] === '1' && ! app(FournisseurSms::class)->estConfigure()) {
            $erreurs['sms.actif'] = 'Aucun fournisseur SMS n\'est branché : le SMS ne peut pas être activé pour l\'instant.';
        }

        return $erreurs;
    }

    /** RG-22 : les réglages sensibles demandent de ressaisir le mot de passe (5 essais par heure). */
    private static function verifierMotDePasse(Utilisateur $auteur, ?string $motDePasse): void
    {
        $cle = 'reglages-mot-de-passe:'.$auteur->id;
        if (RateLimiter::tooManyAttempts($cle, 5)) {
            throw new ReglageInvalide(['mot_de_passe' => 'Trop d\'essais. Réessayez dans quelques minutes.']);
        }
        if ($motDePasse === null || $motDePasse === '' || ! Hash::check($motDePasse, $auteur->mot_de_passe)) {
            RateLimiter::hit($cle, 900);
            throw new ReglageInvalide(['mot_de_passe' => 'Mot de passe incorrect. Rien n\'a été modifié.']);
        }
        RateLimiter::clear($cle);
    }
}
