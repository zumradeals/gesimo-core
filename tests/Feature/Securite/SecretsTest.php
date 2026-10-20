<?php

use App\Noyau\Models\JournalAudit;
use App\Noyau\Services\Audit;
use App\Parametres\Models\Parametre;
use App\Parametres\Services\Parametres;
use Illuminate\Support\Facades\DB;

const SECRET = 'cle-factice-pour-test-9f3a';

it('chiffre un secret : la base ne contient jamais la valeur en clair', function () {
    Parametres::definir('geniuspay.cle_secrete', SECRET, secret: true, groupe: 'paiement');

    $enBase = DB::table('parametre')->where('cle', 'geniuspay.cle_secrete')->value('valeur');

    expect($enBase)->not->toBe(SECRET)
        ->and($enBase)->not->toContain('factice')
        ->and($enBase)->not->toContain('cle-factice');

    // Recherche dans TOUTES les tables de la base : le secret n'apparaît nulle part.
    foreach (['parametre', 'parametre_historique', 'journal_audit'] as $table) {
        expect(json_encode(DB::table($table)->get()))->not->toContain('cle-factice-pour-test');
    }
});

it('restitue le secret à l\'application, et seulement à elle', function () {
    Parametres::definir('geniuspay.cle_secrete', SECRET, secret: true);

    expect(Parametres::obtenir('geniuspay.cle_secrete'))->toBe(SECRET);

    $parametre = Parametre::where('cle', 'geniuspay.cle_secrete')->first();
    expect(Parametres::affichable($parametre))->toBe(Parametres::MASQUE)
        ->and($parametre->toJson())->not->toContain('valeur');
});

it('un réglage ordinaire reste lisible et modifiable', function () {
    Parametres::definir('reservation.duree_blocage_heures', '2');
    Parametres::definir('reservation.duree_blocage_heures', '3');

    expect(Parametres::obtenir('reservation.duree_blocage_heures'))->toBe('3')
        ->and(Parametres::obtenir('absent', 'defaut'))->toBe('defaut');
});

it('RG-22 : trace chaque changement sans jamais écrire la valeur d\'un secret', function () {
    $auteur = creerUtilisateur();
    Parametres::definir('reservation.duree_blocage_heures', '2', $auteur);
    Parametres::definir('reservation.duree_blocage_heures', '3', $auteur);
    Parametres::definir('email.mot_de_passe', 'mdp-smtp-tres-secret', $auteur, secret: true);
    Parametres::definir('email.mot_de_passe', 'nouveau-mdp-smtp', $auteur);

    $historique = DB::table('parametre_historique')->orderBy('id')->get();

    expect($historique)->toHaveCount(4)
        ->and($historique[1]->ancienne_valeur)->toBe('2')
        ->and($historique[1]->nouvelle_valeur)->toBe('3')
        ->and($historique[2]->nouvelle_valeur)->toBeNull()
        ->and($historique[3]->est_secret)->toBeTrue()
        ->and(json_encode($historique))->not->toContain('smtp-tres-secret')
        ->and(json_encode($historique))->not->toContain('nouveau-mdp');

    // Un secret ne redevient jamais un réglage lisible.
    expect(DB::table('parametre')->where('cle', 'email.mot_de_passe')->value('valeur'))->not->toContain('nouveau-mdp');
    expect(Parametres::obtenir('email.mot_de_passe'))->toBe('nouveau-mdp-smtp');
});

it('refuse de modifier ou supprimer l\'historique des réglages et le journal d\'audit', function () {
    Parametres::definir('reservation.duree_blocage_heures', '2');
    $ligneHistorique = DB::table('parametre_historique')->value('id');
    $ligneAudit = DB::table('journal_audit')->value('id');

    expect(codeRefusBase(fn () => DB::table('parametre_historique')->where('id', $ligneHistorique)->update(['nouvelle_valeur' => '99'])))->toBe('23000');
    expect(codeRefusBase(fn () => DB::table('parametre_historique')->where('id', $ligneHistorique)->delete()))->toBe('23000');
    expect(codeRefusBase(fn () => DB::table('journal_audit')->where('id', $ligneAudit)->update(['action' => 'autre'])))->toBe('23000');
    expect(codeRefusBase(fn () => DB::table('journal_audit')->where('id', $ligneAudit)->delete()))->toBe('23000');
    expect(codeRefusBase(fn () => DB::statement('TRUNCATE journal_audit')))->toBe('23000');
});

it('journal d\'audit : enregistre qui, quoi, sur quel objet, et masque les données sensibles', function () {
    $auteur = creerUtilisateur();
    $residence = creerResidence();

    Audit::enregistrer('residence.suspendue', $residence, ['motif' => 'Fraude', 'mot_de_passe' => 'abc', 'cle_api' => 'xyz', 'sous' => ['token' => 't']], $auteur);

    $ligne = JournalAudit::latest('id')->first();
    expect($ligne->utilisateur_id)->toBe($auteur->id)
        ->and($ligne->action)->toBe('residence.suspendue')
        ->and($ligne->objet_type)->toBe('residence')
        ->and($ligne->objet_id)->toBe($residence->id)
        ->and($ligne->created_at)->not->toBeNull()
        ->and($ligne->details['motif'])->toBe('Fraude')
        ->and($ligne->details['mot_de_passe'])->toBe('***')
        ->and($ligne->details['cle_api'])->toBe('***')
        ->and($ligne->details['sous']['token'])->toBe('***');
});

it('stocke le mot de passe haché et jamais en clair', function () {
    $utilisateur = creerUtilisateur();

    $enBase = DB::table('utilisateur')->where('id', $utilisateur->id)->value('mot_de_passe');

    expect($enBase)->not->toBe('mot-de-passe-de-test')
        ->and(password_verify('mot-de-passe-de-test', $enBase))->toBeTrue()
        ->and($utilisateur->toArray())->not->toHaveKey('mot_de_passe');
});
