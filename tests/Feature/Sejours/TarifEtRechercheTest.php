<?php

use App\Noyau\Enums\Role;
use App\Noyau\Services\Format;
use App\Sejours\Livewire\FicheLogement;
use App\Sejours\Livewire\Recapitulatif;
use App\Sejours\Livewire\Recherche;
use App\Sejours\Models\Equipement;
use App\Sejours\Services\Disponibilites;
use App\Sejours\Services\TarifCalculateur;
use Livewire\Livewire;

it('calcule le prix : fête > week-end > prix normal, nuit par nuit', function () {
    $unite = creerLogement(15000, 20000);
    $vendredi = prochainVendredi();
    // Fête : le dimanche suivant le week-end.
    $unite->tarifs()->create(['type_periode' => 'fete', 'libelle' => 'Fête', 'prix_nuit' => 30000, 'date_debut' => $vendredi->addDays(2), 'date_fin' => $vendredi->addDays(2)]);
    $unite = $unite->fresh();
    $calcul = new TarifCalculateur;

    // ven (week-end 20 000) + sam (week-end 20 000) + dim (fête 30 000) + lun (normal 15 000)
    expect($calcul->total($unite, $vendredi, $vendredi->addDays(4)))->toBe(85000)
        ->and($calcul->total($unite, $vendredi->addDays(3), $vendredi->addDays(5)))->toBe(30000); // lun + mar normaux
    $detail = $calcul->detail($unite, $vendredi, $vendredi->addDays(3));
    expect(array_column($detail, 'type'))->toBe(['week_end', 'week_end', 'fete']);
});

it('sans prix week-end, toutes les nuits sont au prix normal ; sans prix normal, pas de prix', function () {
    $vendredi = prochainVendredi();
    $sansWeekEnd = creerLogement(15000, null);
    expect((new TarifCalculateur)->total($sansWeekEnd, $vendredi, $vendredi->addDays(3)))->toBe(45000);

    $sansPrix = creerResidence()->unites()->create(['nom' => 'Sans prix', 'type' => 'studio', 'capacite' => 2]);
    expect((new TarifCalculateur)->total($sansPrix->fresh(), $vendredi, $vendredi->addDays(3)))->toBeNull();
});

it('la recherche ne montre que les logements réellement libres, avec le prix total', function () {
    $vendredi = prochainVendredi();
    $libre = creerLogement();
    $pris = creerLogement();
    insererReservationSql($pris, $vendredi->toDateString(), $vendredi->addDays(3)->toDateString());

    $resultats = app(Disponibilites::class)->rechercher($vendredi, $vendredi->addDays(3), null, 2);

    expect($resultats->pluck('unite.id')->all())->toBe([$libre->id])
        ->and($resultats->first()['total'])->toBe(55000);
});

it('la recherche cache les résidences non vérifiées, les logements retirés et trop petits', function () {
    $vendredi = prochainVendredi();
    creerLogement(15000, 20000, ['statut_verification' => 'en_attente']);
    creerLogement(15000, 20000, ['statut_verification' => 'suspendue']);
    $retire = creerLogement();
    $retire->update(['active' => false]);
    $petit = creerLogement(15000, 20000, [], 1);
    $bon = creerLogement();

    $ids = app(Disponibilites::class)->rechercher($vendredi, $vendredi->addDays(2), null, 2)->pluck('unite.id')->all();

    expect($ids)->toBe([$bon->id])->and($ids)->not->toContain($petit->id);
});

it('filtre par commune, budget du séjour et équipements', function () {
    $vendredi = prochainVendredi();
    $cher = creerLogement(40000, 50000, ['commune' => 'Marcory']);
    $climatise = creerLogement(15000, 20000, ['commune' => 'Cocody']);
    $climatise->equipements()->attach(Equipement::where('code', 'climatisation')->value('id'));
    $simple = creerLogement(12000, 12000, ['commune' => 'Cocody']);
    $service = app(Disponibilites::class);
    $a = $vendredi;
    $d = $vendredi->addDays(2);

    expect($service->rechercher($a, $d, 'Marcory')->pluck('unite.id')->all())->toBe([$cher->id]);
    expect($service->rechercher($a, $d, 'Cocody', 1, 30000)->pluck('unite.id')->all())->toBe([$simple->id]); // 24 000 ≤ 30 000 ; 40 000 > 30 000
    expect($service->rechercher($a, $d, null, 1, null, [Equipement::where('code', 'climatisation')->value('id')])->pluck('unite.id')->all())->toBe([$climatise->id]);
    expect($service->rechercher($a, $d, null, 1, 10000))->toBeEmpty();
});

it('affiche le même prix total dans la recherche, la fiche et le récapitulatif', function () {
    $vendredi = prochainVendredi();
    $unite = creerLogement(15000, 20000);
    $arrivee = $vendredi->toDateString();
    $depart = $vendredi->addDays(3)->toDateString();
    $prix = Format::fcfa(55000);

    Livewire::test(Recherche::class)
        ->set('arrivee', $arrivee)->set('depart', $depart)->set('personnes', 2)->call('chercher')
        ->assertSee($prix)->assertSee($unite->nom);

    Livewire::test(FicheLogement::class, ['unite' => $unite])
        ->set('arrivee', $arrivee)->set('depart', $depart)->assertSee($prix);

    Livewire::actingAs(creerUtilisateur(Role::Client))
        ->withQueryParams(['arrivee' => $arrivee, 'depart' => $depart, 'personnes' => 2])
        ->test(Recapitulatif::class, ['unite' => $unite])->assertSee($prix);
});

it('la recherche refuse des dates incohérentes avec un message clair', function () {
    $vendredi = prochainVendredi();

    Livewire::test(Recherche::class)
        ->set('arrivee', $vendredi->toDateString())->set('depart', $vendredi->toDateString())->call('chercher')
        ->assertSee('La date de départ doit être après la date d\'arrivée.');
});

it('la fiche d\'un logement non vérifié n\'est pas publique', function () {
    $unite = creerLogement(15000, 20000, ['statut_verification' => 'en_attente']);

    $this->get(route('logement', $unite))->assertNotFound();
});
