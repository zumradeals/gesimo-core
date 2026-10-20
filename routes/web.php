<?php

use App\Noyau\Livewire\AccepterInvitation;
use App\Noyau\Livewire\Admin\JournalAudit as PageJournal;
use App\Noyau\Livewire\Admin\ModelesEmails;
use App\Noyau\Livewire\Compte;
use App\Noyau\Livewire\Connexion;
use App\Noyau\Livewire\ConnexionParLien;
use App\Noyau\Livewire\Contact;
use App\Noyau\Livewire\Inscription;
use App\Noyau\Livewire\Installation;
use App\Noyau\Livewire\PageStatique;
use App\Noyau\Livewire\Profil;
use App\Noyau\Services\Connexion as ServiceConnexion;
use App\Paiement\Http\RecuReservation;
use App\Paiement\Http\WebhookGeniusPay;
use App\Paiement\Livewire\AnnulerReservation;
use App\Paiement\Livewire\ArriveesDuJour;
use App\Paiement\Livewire\Confirmation;
use App\Paiement\Livewire\Controle;
use App\Paiement\Livewire\PagePaiement;
use App\Paiement\Livewire\PaiementsEtEcritures;
use App\Paiement\Livewire\RetourPaiement;
use App\Paiement\Livewire\ReversementsAdmin;
use App\Paiement\Livewire\SommesDues;
use App\Parametres\Livewire\EspaceParametres;
use App\Sejours\Http\TelechargerDocument;
use App\Sejours\Livewire\Admin\ListeVerifications;
use App\Sejours\Livewire\Admin\LitigesAdmin;
use App\Sejours\Livewire\Admin\TableauDeBord;
use App\Sejours\Livewire\Admin\ToutesLesReservations;
use App\Sejours\Livewire\Admin\ValiderResidence;
use App\Sejours\Livewire\CalendrierGerant;
use App\Sejours\Livewire\DetailReservation;
use App\Sejours\Livewire\DonnerAvis;
use App\Sejours\Livewire\EditerResidence;
use App\Sejours\Livewire\FicheLogement;
use App\Sejours\Livewire\GestionPersonnel;
use App\Sejours\Livewire\MesReservations;
use App\Sejours\Livewire\MesResidences;
use App\Sejours\Livewire\OuvrirLitige;
use App\Sejours\Livewire\Recapitulatif;
use App\Sejours\Livewire\Recherche;
use App\Sejours\Livewire\ReservationsGerant;
use App\Sejours\Livewire\ReservationTelephone;
use Illuminate\Support\Facades\Route;

// --- Public --------------------------------------------------------------------------------
Route::livewire('/', Recherche::class)->name('accueil');
Route::livewire('/recherche', Recherche::class)->name('recherche');
Route::livewire('/logement/{unite}', FicheLogement::class)->name('logement');

Route::middleware('guest')->group(function () {
    Route::livewire('/connexion', Connexion::class)->name('connexion');
    Route::livewire('/inscription', Inscription::class)->name('inscription');
});
// Assistant d'installation du premier lancement (404 une fois terminé)
Route::livewire('/installation', Installation::class)->name('installation');

// Pages d'information
Route::livewire('/contact', Contact::class)->name('contact');
foreach (['a-propos', 'faq', 'conditions', 'confidentialite'] as $page) {
    Route::livewire('/'.$page, PageStatique::class)->defaults('page', $page)->name($page);
}
Route::livewire('/devenir-partenaire', PageStatique::class)->defaults('page', 'devenir-partenaire')->name('devenir-partenaire');
Route::livewire('/connexion/lien/{jeton}', ConnexionParLien::class)->name('connexion.lien');
Route::livewire('/invitation/{jeton}', AccepterInvitation::class)->name('invitation');

Route::post('/webhooks/geniuspay', WebhookGeniusPay::class)->middleware('throttle:120,1')->name('webhooks.geniuspay');

Route::post('/deconnexion', function (ServiceConnexion $connexion) {
    $connexion->deconnecter();

    return redirect()->route('accueil');
})->middleware('auth')->name('deconnexion');

// --- Toute personne connectée ------------------------------------------------------------------
Route::middleware('auth')->group(function () {
    Route::livewire('/compte', Compte::class)->name('compte');
    Route::livewire('/compte/profil', Profil::class)->name('profil');
});

// --- Client -----------------------------------------------------------------------------------
Route::middleware(['auth', 'role:client'])->group(function () {
    Route::livewire('/reserver/{unite}', Recapitulatif::class)->name('reserver');
    Route::livewire('/mes-reservations', MesReservations::class)->name('mes-reservations');
    Route::livewire('/payer/{reservation}', PagePaiement::class)->name('payer');
    Route::livewire('/paiement/retour/{reservation}', RetourPaiement::class)->name('paiement.retour');
    Route::livewire('/reservation/{reservation}/confirmation', Confirmation::class)->name('reservation.confirmation');
    Route::livewire('/reservation/{reservation}/annuler', AnnulerReservation::class)->name('reservation.annuler');
    Route::livewire('/reservation/{reservation}', DetailReservation::class)->whereNumber('reservation')->name('reservation.detail');
    Route::livewire('/reservation/{reservation}/avis', DonnerAvis::class)->name('reservation.avis');
    Route::livewire('/reservation/{reservation}/probleme', OuvrirLitige::class)->name('reservation.probleme');
    Route::get('/reservation/{reservation}/recu', RecuReservation::class)->name('reservation.recu');
});

// --- Gérant (et personnel pour le calendrier) -----------------------------------------------------
Route::middleware(['auth', 'role:gerant,personnel'])->group(function () {
    Route::livewire('/gerant/calendrier', CalendrierGerant::class)->name('gerant.calendrier');
    Route::livewire('/gerant/arrivees', ArriveesDuJour::class)->name('gerant.arrivees');
});
Route::middleware(['auth', 'role:gerant'])->prefix('gerant')->group(function () {
    Route::livewire('/residences', MesResidences::class)->name('gerant.residences');
    Route::livewire('/residences/nouvelle', EditerResidence::class)->name('gerant.residence.nouvelle');
    Route::livewire('/residences/{residence}', EditerResidence::class)->name('gerant.residence');
    Route::livewire('/telephone', ReservationTelephone::class)->name('gerant.telephone');
    Route::livewire('/personnel', GestionPersonnel::class)->name('gerant.personnel');
    Route::livewire('/argent', SommesDues::class)->name('gerant.argent');
    Route::livewire('/reservations', ReservationsGerant::class)->name('gerant.reservations');
    Route::livewire('/reservations/{reservation}/probleme', OuvrirLitige::class)->name('gerant.probleme');
    Route::livewire('/aide', PageStatique::class)->defaults('page', 'aide-gerant')->name('gerant.aide');
});

// --- Admin GESIMO ----------------------------------------------------------------------------------
Route::middleware(['auth', 'role:admin'])->prefix('admin')->group(function () {
    Route::livewire('/', TableauDeBord::class)->name('admin.tableau');
    Route::livewire('/reservations', ToutesLesReservations::class)->name('admin.reservations');
    Route::livewire('/litiges', LitigesAdmin::class)->name('admin.litiges');
    Route::livewire('/journal', PageJournal::class)->name('admin.journal');
    Route::livewire('/messages', ModelesEmails::class)->name('admin.messages');
    Route::livewire('/reglages/{rubrique?}', EspaceParametres::class)->name('admin.reglages');
    Route::livewire('/residences', ListeVerifications::class)->name('admin.residences');
    Route::livewire('/residences/{residence}', ValiderResidence::class)->name('admin.residence');
    Route::get('/documents/{document}', TelechargerDocument::class)->name('admin.document');
    Route::livewire('/paiements', PaiementsEtEcritures::class)->name('admin.paiements');
    Route::livewire('/reversements', ReversementsAdmin::class)->name('admin.reversements');
    Route::livewire('/controle', Controle::class)->name('admin.controle');
});
