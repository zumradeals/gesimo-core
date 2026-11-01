<?php

use App\Noyau\Services\Format;
use App\Parametres\Services\Parametres;
use App\Sejours\Livewire\Recherche;
use App\Sejours\Models\Equipement;
use App\Sejours\Models\Unite;
use App\Sejours\Services\Disponibilites;
use Livewire\Livewire;

/** Catalogue Logements v3 (docs/contrats/logements-v3.md). */
function logementType(string $type, array $residence = [], int $prix = 15000, string $nom = 'Logement'): Unite
{
    $u = creerLogement($prix, null, $residence);
    $u->update(['type' => $type, 'nom' => $nom]);

    return $u->fresh();
}

it('présente un titre court au lieu du grand bandeau, avec la recherche arrivée + nuits', function () {
    creerLogement();
    $this->get(route('recherche'))->assertOk()
        ->assertSee('Trouvez votre logement')->assertSee('Résidences meublées en Côte d\'Ivoire', false)
        ->assertSee('Nombre de nuits')->assertDontSee('lo-ambiance', false)->assertDontSee('Le bon logement')
        ->assertDontSee('id="depart"', false)->assertSee('id="arrivee"', false)->assertSee('novalidate', false);
});

it('calcule le départ à partir de l\'arrivée et du nombre de nuits, avec les mêmes limites que la réservation', function () {
    creerLogement();
    $v = prochainVendredi();
    $page = Livewire::test(Recherche::class)->assertSee('Choisissez une date d\'arrivée', false)
        ->set('arrivee', $v->toDateString())->assertSet('nuits', 1)->assertSet('depart', $v->addDay()->toDateString());
    $page->call('plusNuit')->call('plusNuit')->assertSet('nuits', 3)->assertSet('depart', $v->addDays(3)->toDateString())
        ->assertSee('Départ : ')->assertSee('avant 11 h');
    $page->call('moinsNuit')->call('moinsNuit')->call('moinsNuit')->assertSet('nuits', 1);          // jamais sous le minimum
    $page->call('fixerNuits', 99)->assertSet('nuits', 30);                                          // jamais au-dessus du maximum
    $page->set('arrivee', $v->addDays(7)->toDateString())->assertSet('nuits', 30)->assertSet('depart', $v->addDays(37)->toDateString());

    Parametres::definir('reservation.sejour_max_nuits', '5');
    Livewire::test(Recherche::class)->call('fixerNuits', 99)->assertSet('nuits', 5);
});

it('un lien « arrivée + nuits » (formulaire de l\'accueil) lance la recherche et donne le prix total et moyen', function () {
    creerLogement(15000, 20000)->update(['nom' => 'Séjour moyen']);
    $v = prochainVendredi();

    Livewire::withQueryParams(['arrivee' => $v->toDateString(), 'nuits' => 3, 'personnes' => 2])->test(Recherche::class)
        ->assertSet('depart', $v->addDays(3)->toDateString())->assertSet('lancee', true)
        ->assertSee('Séjour moyen')->assertSee('Montant du séjour')->assertSee(Format::fcfa(55000))
        ->assertSee('soit '.Format::fcfa(18333).' / nuit en moyenne', false)->assertSee('pour 3 nuits')
        ->assertSee('1 logement libre pour 3 nuits');
});

it('refuse un nombre de nuits hors limites et une arrivée passée avec un message simple', function () {
    creerLogement();
    $v = prochainVendredi();
    Livewire::withQueryParams(['arrivee' => $v->toDateString(), 'depart' => $v->addDays(40)->toDateString()])->test(Recherche::class)
        ->assertSet('lancee', false)->assertSee('Le nombre de nuits doit être compris entre 1 et 30.')->assertSee('Tarif de référence');
    Livewire::test(Recherche::class)->set('arrivee', now()->subDays(3)->toDateString())->call('chercher')
        ->assertSee('La date d\'arrivée ne peut pas être dans le passé.')->assertSet('lancee', false);
});

it('filtre par type de logement, en ignorant un type inconnu', function () {
    logementType('studio', nom: 'Un studio');
    logementType('appartement', nom: 'Un appartement');
    logementType('chambre', nom: 'Une chambre');

    expect(app(Disponibilites::class)->catalogue(types: ['studio'])->pluck('unite.nom')->all())->toBe(['Un studio']);
    expect(app(Disponibilites::class)->catalogue(types: ['studio', 'chambre'])->pluck('unite.nom')->sort()->values()->all())->toBe(['Un studio', 'Une chambre']);

    Livewire::test(Recherche::class)->assertSee('Type de logement')->assertSee('Un studio')->assertSee('Un appartement')
        ->call('basculerType', 'studio')->assertSee('Un studio')->assertDontSee('Un appartement')->assertDontSee('Une chambre')
        ->call('basculerType', 'villa')->assertSet('types', ['studio'])
        ->call('basculerType', 'studio')->assertSet('types', [])->assertSee('Un appartement');
    Livewire::withQueryParams(['types' => ['chambre', 'piratage']])->test(Recherche::class)->assertSet('types', ['chambre'])->assertSee('Une chambre')->assertDontSee('Un studio');
});

it('le filtre par type s\'applique aussi après une recherche datée', function () {
    $v = prochainVendredi();
    logementType('studio', nom: 'Studio libre');
    logementType('appartement', nom: 'Appartement libre');
    Livewire::withQueryParams(['arrivee' => $v->toDateString(), 'nuits' => 2, 'types' => ['studio']])->test(Recherche::class)
        ->assertSee('Studio libre')->assertDontSee('Appartement libre');
});

it('propose les communes avec le nombre de logements, sans compter ce qui n\'est pas publié', function () {
    logementType('studio', ['commune' => 'Cocody'], nom: 'A');
    logementType('studio', ['commune' => 'Cocody'], nom: 'B');
    logementType('studio', ['commune' => 'Marcory'], nom: 'C');
    logementType('studio', ['commune' => 'Plateau', 'statut_verification' => 'en_attente'], nom: 'Privé');
    $inactif = logementType('studio', ['commune' => 'Marcory'], nom: 'Inactif');
    $inactif->update(['active' => false]);

    expect(app(Disponibilites::class)->logementsParCommune()->all())->toBe(['Cocody' => 2, 'Marcory' => 1]);

    Livewire::test(Recherche::class)->assertSee('Toutes')->assertSeeHtml('aria-pressed="true"')
        ->assertSeeInOrder(['Toutes', '3', 'Cocody', '2', 'Marcory', '1'])
        ->call('choisirCommune', 'Marcory')->assertSet('commune', 'Marcory')->assertSee('C')->assertDontSee('Privé')
        ->call('choisirCommune', '')->assertSet('commune', '');
});

it('montre les filtres actifs en puces, chacune avec son retrait, et « Tout effacer » garde les dates', function () {
    $wifi = Equipement::where('code', 'wifi')->firstOrFail();
    $u = logementType('studio', nom: 'Équipé');
    $u->equipements()->attach($wifi->id);
    logementType('appartement', nom: 'Sans équipement');
    $v = prochainVendredi();

    $page = Livewire::withQueryParams(['arrivee' => $v->toDateString(), 'nuits' => 2])->test(Recherche::class)
        ->assertDontSee('Filtres actifs')
        ->call('basculerEquipement', $wifi->id)->call('basculerType', 'studio')->set('budget', '90000')
        ->assertSee('Filtres actifs')->assertSee('Retirer le filtre Wi-Fi')->assertSee('Retirer le filtre Studio')->assertSee('Retirer le filtre Budget max')
        ->assertSee('Tout effacer')->assertSee('(3)')->assertSee('Équipé')->assertDontSee('Sans équipement');

    $page->call('retirerBudget')->assertSet('budget', '')->assertDontSee('Retirer le filtre Budget')->assertSee('(2)');
    $page->call('basculerEquipement', $wifi->id)->assertSet('equipements', [])->assertSee('(1)');
    $page->call('reinitialiser')->assertSet('types', [])->assertSet('equipements', [])->assertSet('arrivee', $v->toDateString())->assertSet('nuits', 2)
        ->assertDontSee('Filtres actifs')->assertSee('Sans équipement');
});

it('propose des boutons précis quand rien ne correspond', function () {
    $wifi = Equipement::where('code', 'wifi')->firstOrFail();
    logementType('studio', ['commune' => 'Cocody'], nom: 'Seul');
    $v = prochainVendredi();

    Livewire::test(Recherche::class)->call('choisirCommune', 'Marcory')->assertSee('Essayons d’autres critères.')->assertSee('Toutes les communes');
    Livewire::test(Recherche::class)->call('basculerEquipement', $wifi->id)->assertSee('Essayons d’autres critères.')->assertSee('Retirer « Wi-Fi »');
    Livewire::test(Recherche::class)->set('personnes', 40)->call('chercher')->assertSee('Essayons d’autres critères.')->assertSee('Élargir ma recherche');
    Livewire::withQueryParams(['arrivee' => $v->toDateString(), 'nuits' => 2, 'commune' => 'Marcory'])->test(Recherche::class)
        ->assertSee('Aucun logement libre à ces dates.')->assertSee('Changer les dates')->assertSee('Toutes les communes');
});

it('accorde correctement le nombre de logements et nomme le tri par défaut « Recommandés »', function () {
    logementType('studio', nom: 'Unique');
    Livewire::test(Recherche::class)->assertSee('1 logement présenté')->assertDontSee('1 logements')
        ->assertSee('Recommandés')->assertSee('Prix décroissant')->assertDontSee('Prix croissant');
    logementType('studio', nom: 'Second');
    Livewire::test(Recherche::class)->assertSee('2 logements présentés');
});

it('le budget reste inactif sans dates et s\'active avec une recherche datée', function () {
    creerLogement();
    $v = prochainVendredi();
    Livewire::test(Recherche::class)->assertSeeHtml('id="budget"')->assertSee('Choisissez vos dates pour filtrer sur le montant total.')->assertDontSee('Retirer le filtre Budget');
    Livewire::withQueryParams(['arrivee' => $v->toDateString(), 'nuits' => 2])->test(Recherche::class)->assertSee('Le montant total, pour toutes les nuits et tous les voyageurs.');
});

it('le formulaire de l\'accueil demande l\'arrivée et le nombre de nuits, dans les limites réglées', function () {
    $this->get(route('accueil'))->assertOk()
        ->assertSee('name="nuits"', false)->assertSee('Nombre de nuits')->assertSee('id="accueil-arrivee"', false)
        ->assertDontSee('name="depart"', false)->assertDontSee('id="accueil-depart"', false)
        ->assertSee('min="1"', false)->assertSee('max="30"', false);
    Parametres::definir('reservation.sejour_max_nuits', '10');
    $this->get(route('accueil'))->assertSee('max="10"', false);
});

it('les liens vers la fiche gardent l\'arrivée, le départ calculé et les voyageurs', function () {
    $u = creerLogement();
    $v = prochainVendredi();
    Livewire::withQueryParams(['arrivee' => $v->toDateString(), 'nuits' => 3, 'personnes' => 2])->test(Recherche::class)
        ->assertSeeHtml('arrivee='.$v->toDateString())->assertSeeHtml('depart='.$v->addDays(3)->toDateString())->assertSeeHtml('personnes=2');
});
