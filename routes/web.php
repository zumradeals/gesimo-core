<?php

use App\Noyau\Livewire\AccepterInvitation;
use App\Noyau\Livewire\Compte;
use App\Noyau\Livewire\Connexion;
use App\Noyau\Livewire\ConnexionParLien;
use App\Noyau\Livewire\Inscription;
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
use App\Sejours\Http\TelechargerDocument;
use App\Sejours\Livewire\Admin\ListeVerifications;
use App\Sejours\Livewire\Admin\ValiderResidence;
use App\Sejours\Livewire\CalendrierGerant;
use App\Sejours\Livewire\EditerResidence;
use App\Sejours\Livewire\FicheLogement;
use App\Sejours\Livewire\GestionPersonnel;
use App\Sejours\Livewire\MesReservations;
use App\Sejours\Livewire\MesResidences;
use App\Sejours\Livewire\Recapitulatif;
use App\Sejours\Livewire\Recherche;
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
});

// --- Client -----------------------------------------------------------------------------------
Route::middleware(['auth', 'role:client'])->group(function () {
    Route::livewire('/reserver/{unite}', Recapitulatif::class)->name('reserver');
    Route::livewire('/mes-reservations', MesReservations::class)->name('mes-reservations');
    Route::livewire('/payer/{reservation}', PagePaiement::class)->name('payer');
    Route::livewire('/paiement/retour/{reservation}', RetourPaiement::class)->name('paiement.retour');
    Route::livewire('/reservation/{reservation}/confirmation', Confirmation::class)->name('reservation.confirmation');
    Route::livewire('/reservation/{reservation}/annuler', AnnulerReservation::class)->name('reservation.annuler');
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
});

// --- Admin GESIMO ----------------------------------------------------------------------------------
Route::middleware(['auth', 'role:admin'])->prefix('admin')->group(function () {
    Route::livewire('/residences', ListeVerifications::class)->name('admin.residences');
    Route::livewire('/residences/{residence}', ValiderResidence::class)->name('admin.residence');
    Route::get('/documents/{document}', TelechargerDocument::class)->name('admin.document');
    Route::livewire('/paiements', PaiementsEtEcritures::class)->name('admin.paiements');
    Route::livewire('/reversements', ReversementsAdmin::class)->name('admin.reversements');
    Route::livewire('/controle', Controle::class)->name('admin.controle');
});
