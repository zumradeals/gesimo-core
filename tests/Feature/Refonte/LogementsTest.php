<?php

use App\Noyau\Services\Format;
use App\Paiement\Models\MiseEnAvant;
use App\Sejours\Livewire\Recherche;
use App\Sejours\Models\Equipement;
use App\Sejours\Services\Disponibilites;
use Livewire\Livewire;

it('présente sans dates uniquement le catalogue publiable avec un tarif de référence', function () {
    $bon = creerLogement(17000, 25000);
    $bon->update(['nom' => 'Adresse publique']);
    $attente = creerLogement(10000, null, ['statut_verification' => 'en_attente', 'commune' => 'Commune privée']);
    $inactif = creerLogement();
    $inactif->update(['active' => false]);
    $nonIdentifie = creerLogement();
    $nonIdentifie->residence->gerant->profilGerant->documents()->delete();
    $sansPrix = creerUnite();
    insererReservationSql($bon, prochainVendredi()->toDateString(), prochainVendredi()->addDays(2)->toDateString());

    expect(app(Disponibilites::class)->catalogue()->pluck('unite.id')->all())->toBe([$bon->id]);
    Livewire::test(Recherche::class)->assertSee('Adresse publique')->assertSee('Tarif de référence')
        ->assertSee(Format::fcfa(17000))->assertDontSee('Montant du séjour')->assertDontSee('Commune privée')
        ->assertSeeHtml('id="budget"')->assertSet('lancee', false);
});

it('filtre le catalogue sans dates par commune capacité et tous les équipements', function () {
    $wifi = Equipement::where('code', 'wifi')->firstOrFail();
    $clim = Equipement::where('code', 'climatisation')->firstOrFail();
    $bon = creerLogement(23000, null, ['commune' => 'Cocody'], 4);
    $bon->update(['nom' => 'Appartement équipé']);
    $bon->equipements()->attach([$wifi->id, $clim->id]);
    $autre = creerLogement(12000, null, ['commune' => 'Marcory']);
    $autre->update(['nom' => 'Autre commune']);
    $partiel = creerLogement(12000, null, ['commune' => 'Cocody'], 4);
    $partiel->equipements()->attach($wifi->id);
    creerLogement(12000, null, ['commune' => 'Cocody'], 1);

    expect(app(Disponibilites::class)->catalogue('Cocody', 3, [$wifi->id, $clim->id])->pluck('unite.id')->all())->toBe([$bon->id]);
    Livewire::withQueryParams(['commune' => 'Cocody'])->test(Recherche::class)->assertSee('Appartement équipé')->assertDontSee('Autre commune')
        ->set('equipements', [$wifi->id, $clim->id])->set('personnes', 3)->call('chercher')->assertSee('Appartement équipé')
        ->set('equipements', [999999])->assertSee('Essayons d’autres critères.')->call('reinitialiser')->assertSee('Autre commune');
});

it('remplace les références par les vrais totaux et retire les logements occupés après recherche', function () {
    $libre = creerLogement(15000, 20000);
    $libre->update(['nom' => 'Séjour libre']);
    $pris = creerLogement(10000, null);
    $pris->update(['nom' => 'Séjour occupé']);
    $a = prochainVendredi();
    insererReservationSql($pris, $a->toDateString(), $a->addDays(3)->toDateString());

    Livewire::test(Recherche::class)->assertSee('Séjour occupé')->assertSee('Tarif de référence')
        ->set('arrivee', $a->toDateString())->set('depart', $a->addDays(3)->toDateString())->call('chercher')
        ->assertSee('Séjour libre')->assertDontSee('Séjour occupé')->assertSee('Montant du séjour')->assertDontSee('Tarif de référence')
        ->assertSee(Format::fcfa(55000))->assertSeeHtml('arrivee='.$a->toDateString())
        ->set('budget', '50000')->assertDontSee('Séjour libre')->call('reinitialiser')->assertSee('Séjour libre')
        ->assertSet('arrivee', $a->toDateString())->assertSet('depart', $a->addDays(3)->toDateString());
});

it('ne transforme jamais des dates incomplètes mal formées ou passées en disponibilité', function (string $a, string $d) {
    creerLogement();
    Livewire::withQueryParams(['arrivee' => $a, 'depart' => $d])->test(Recherche::class)
        ->assertSet('lancee', false)->assertSee('Tarif de référence')->assertDontSee('Montant du séjour');
})->with([
    ['pas-une-date', '2027-01-02'], ['2027-02-30', '2027-03-02'], ['2027-01-02', ''],
    ['', '2027-01-02'], ['2000-01-01', '2000-01-02'], ['2027-01-02', '2027-01-01'],
]);

it('garde les sponsorisés devant le catalogue même lorsque les autres prix sont décroissants', function () {
    $sponsor = creerLogement(10000, null);
    $sponsor->update(['nom' => 'Annonce sponsorisée']);
    $petit = creerLogement(20000, null);
    $petit->update(['nom' => 'Ordinaire économique']);
    $grand = creerLogement(40000, null);
    $grand->update(['nom' => 'Ordinaire premium']);
    MiseEnAvant::create(['residence_id' => $sponsor->residence_id, 'cree_par' => $sponsor->residence->gerant_id,
        'date_debut' => today(), 'date_fin' => today()->addDays(6), 'prix' => 10000, 'offre' => 'Test', 'jours' => 7, 'statut' => 'active']);

    expect(app(Disponibilites::class)->catalogue()->pluck('unite.id')->all())->toBe([$sponsor->id, $petit->id, $grand->id]);
    Livewire::withQueryParams(['tri' => 'prix_desc'])->test(Recherche::class)
        ->assertSeeInOrder(['Annonce sponsorisée', 'Ordinaire premium', 'Ordinaire économique']);
});
