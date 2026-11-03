@php
    $fr = fn (int $m) => \App\Noyau\Services\Format::fcfa($m);
    $n = $resultats->count();
    $pluriel = fn (int $k, string $mot) => $k.' '.$mot.($k > 1 ? 's' : '');
    // Filtres actifs, chacun avec sa façon de le retirer.
    $puces = [];
    foreach ($listeEquipements->whereIn('id', array_map('intval', $equipements)) as $e) {
        $puces[] = ['libelle' => $e->libelle, 'action' => "basculerEquipement({$e->id})"];
    }
    foreach ($types as $t) {
        if (isset($listeTypes[$t])) {
            $puces[] = ['libelle' => $listeTypes[$t], 'action' => "basculerType('{$t}')"];
        }
    }
    if ($nuitsRecherche !== null && $budget !== '') {
        $puces[] = ['libelle' => 'Budget max '.$fr((int) preg_replace('/\D+/', '', $budget)), 'action' => 'retirerBudget'];
    }
    $nbFiltres = count($puces);
    $totalLogements = $communesChiffrees->sum();
@endphp
<div class="logements-v2" x-data="filtresLogements">
    <div class="lo-cont lo-tete">
        <nav class="lo-fil" aria-label="Fil d’Ariane"><a href="{{ route('accueil') }}">Accueil</a> / <span>Logements</span></nav>
        <h1>Trouvez votre logement</h1>
        <p>Résidences meublées en Côte d'Ivoire, avec le prix total affiché avant de payer.</p>
    </div>
    <div class="lo-cont lo-search-wrap">
        <form wire:submit="chercher" class="lo-search" aria-label="Rechercher un logement" novalidate>
            <label for="commune" class="lo-f-commune"><span>Commune</span><select id="commune" wire:model="commune"><option value="">Toutes les communes</option>@foreach($communes as $c)<option>{{ $c }}</option>@endforeach</select></label>
            <label for="arrivee"><span>Arrivée</span><input id="arrivee" type="date" min="{{ now()->toDateString() }}" wire:model.live="arrivee"></label>
            <div class="lo-nuits" role="group" aria-labelledby="nuits-lib"><span id="nuits-lib">Nombre de nuits</span>
                <div class="lo-cpt">
                    <button type="button" wire:click="moinsNuit" @disabled($nuitsChoisies <= $nuitsMin) aria-label="Une nuit de moins">−</button>
                    <output aria-live="polite">@if($arrivee !== '' || $nuits > 0){{ $pluriel($nuitsChoisies, 'nuit') }}@else — @endif</output>
                    <button type="button" wire:click="plusNuit" @disabled($nuitsChoisies >= $nuitsMax) aria-label="Une nuit de plus">+</button>
                </div></div>
            <label for="personnes"><span>Voyageurs</span><input id="personnes" type="number" min="1" max="50" wire:model="personnes" inputmode="numeric"></label>
            <button type="submit" class="lo-btn" data-reseau wire:loading.attr="disabled" wire:target="chercher"><x-ui.icone nom="recherche" classe="ic" /> Rechercher</button>
        </form>
        <p class="lo-depart">@if($dateDepart)Départ : {{ $dateDepart->translatedFormat('D j M Y') }}, avant {{ $heureDepart }}.@else Choisissez une date d'arrivée pour voir les logements libres et le prix total.@endif</p>
        @if($erreur || $errors->any())<div class="lo-error" role="alert">@if($erreur)<p>{{ $erreur }}</p>@endif @foreach($errors->all() as $message)<p>{{ $message }}</p>@endforeach</div>@endif
    </div>
    @if($communesChiffrees->count() > 1)
        <div class="lo-cont"><div class="lo-communes" role="group" aria-label="Communes">
            <button type="button" wire:click="choisirCommune('')" class="{{ $commune === '' ? 'on' : '' }}" aria-pressed="{{ $commune === '' ? 'true' : 'false' }}">Toutes <small>{{ $totalLogements }}</small></button>
            @foreach($communesChiffrees as $nom => $nb)
                <button type="button" wire:click="choisirCommune({{ \Illuminate\Support\Js::from($nom) }})" wire:key="commune-{{ $nom }}" class="{{ $commune === $nom ? 'on' : '' }}" aria-pressed="{{ $commune === $nom ? 'true' : 'false' }}">{{ $nom }} <small>{{ $nb }}</small></button>
            @endforeach
        </div></div>
    @endif
    <div class="lo-cont lo-main" id="catalogue">
        <aside class="lo-side">
            <dialog x-ref="panel" wire:ignore.self class="lo-panel" aria-labelledby="filtres-titre" @close="ferme()" @cancel="ferme()">
                <div class="lo-panel-title"><h2 id="filtres-titre">Affiner votre séjour</h2><button type="button" class="lo-close" @click="fermer()" aria-label="Fermer les filtres">×</button></div>
                <fieldset class="lo-group"><legend>Budget du séjour</legend>
                    <div class="lo-budget"><input id="budget" type="number" min="0" step="1" wire:model.live.debounce.400ms="budget" placeholder="Montant maximum" aria-label="Budget maximum du séjour" aria-describedby="aide-budget" @disabled($nuitsRecherche === null)><span>FCFA</span></div>
                    <p id="aide-budget">{{ $nuitsRecherche === null ? 'Choisissez vos dates pour filtrer sur le montant total.' : 'Le montant total, pour toutes les nuits et tous les voyageurs.' }}</p>
                </fieldset>
                <fieldset class="lo-group"><legend>Les équipements essentiels</legend>
                    @foreach($listeEquipements as $e)<label class="lo-check" wire:key="equipement-{{ $e->id }}"><input type="checkbox" wire:model.live="equipements" value="{{ $e->id }}">{{ $e->libelle }}</label>@endforeach
                </fieldset>
                <fieldset class="lo-group"><legend>Type de logement</legend>
                    @foreach($listeTypes as $code => $libelle)<label class="lo-check" wire:key="type-{{ $code }}"><input type="checkbox" wire:model.live="types" value="{{ $code }}">{{ $libelle }}</label>@endforeach
                </fieldset>
                <div style="padding-top:18px"><button class="lo-reset" type="button" wire:click="reinitialiser">Réinitialiser les filtres ↺</button></div>
                <button class="lo-btn lo-apply" type="button" @click="fermer()">Afficher {{ $pluriel($n, 'logement') }}</button>
            </dialog>
            <div class="lo-help"><b>Besoin d’un repère ?</b><p>Notre équipe peut vous expliquer le fonctionnement de la réservation.</p><a href="{{ route('faq') }}">Comprendre avant de réserver ↗</a></div>
        </aside>
        <section class="lo-results" aria-label="Catalogue de logements" aria-live="polite" aria-atomic="false">
            <div class="lo-result-top">
                <div><h2>{{ $nuitsRecherche === null ? 'Des adresses à découvrir' : 'Votre prochain séjour commence ici' }}</h2>
                    <p>{{ $n }} logement{{ $n > 1 ? 's' : '' }} {{ $nuitsRecherche === null ? 'présenté'.($n > 1 ? 's' : '') : 'libre'.($n > 1 ? 's' : '').' pour '.$nuitsRecherche.' nuit'.($nuitsRecherche > 1 ? 's' : '') }}@if($commune) · {{ $commune }}@endif</p>
                </div>
                <div class="lo-outils">
                    <button x-ref="ouvrir" type="button" class="lo-filter-button" @click="ouvrir()" aria-controls="filtres-titre" :aria-expanded="ouvert.toString()">☷ Filtres @if($nbFiltres)<span>({{ $nbFiltres }})</span>@endif</button>
                    <label class="lo-sort"><span>Trier par</span><select wire:model.live="tri" aria-label="Trier les logements"><option value="recommande">Recommandés</option><option value="prix_desc">Prix décroissant</option></select></label>
                </div>
            </div>
            @if($puces)
                <div class="lo-puces" aria-label="Filtres actifs">
                    @foreach($puces as $p)<span class="lo-puce" wire:key="puce-{{ $loop->index }}">{{ $p['libelle'] }} <button type="button" wire:click="{{ $p['action'] }}" aria-label="Retirer le filtre {{ $p['libelle'] }}">×</button></span>@endforeach
                    <button type="button" class="lo-reset" wire:click="reinitialiser">Tout effacer</button>
                </div>
            @endif
            <div class="lo-note"><x-ui.icone nom="calendrier" classe="ic" /><span>{{ $nuitsRecherche === null ? 'Indiquez vos dates pour connaître les disponibilités et le prix total du séjour. Les tarifs par nuit sont donnés à titre de référence.' : 'Ces logements sont libres à vos dates. Les prix affichés correspondent au montant total du séjour.' }}</span></div>
            <div class="lo-grid lo-squelette" wire:loading.grid wire:target="chercher,choisirCommune,reinitialiser,tri,basculerType,basculerEquipement,retirerBudget" aria-hidden="true">
                @for($k = 0; $k < 4; $k++)<article class="lo-card lo-sq"><div class="lo-photo"></div><div class="lo-card-body"><i></i><i></i><i></i></div></article>@endfor
            </div>
            <div class="lo-grid" wire:loading.remove wire:target="chercher,choisirCommune,reinitialiser,tri,basculerType,basculerEquipement,retirerBudget">
                @forelse($resultats as $ligne)
                    @php
                        $u = $ligne['unite']; $r = $u->residence;
                        $lien = route('logement', ['unite' => $u->id] + ($nuitsRecherche !== null ? ['arrivee' => $arrivee, 'depart' => $depart, 'personnes' => $personnes] : ['personnes' => $personnes]));
                    @endphp
                    <article class="lo-card" wire:key="logement-{{ $u->id }}" data-logement="{{ $u->id }}">
                        <div class="lo-photo"><a href="{{ $lien }}" tabindex="-1" aria-hidden="true"><x-ui.photo :photo="$u->photos->first() ?? $r->photos->first()" classe="h-full" :plat="true" :alt="'Photo de '.$u->nom" sizes="(max-width: 520px) 92vw, (max-width: 1150px) 40vw, 24vw" /></a>@if($ligne['sponsorise'])<span class="lo-sponsored">Sponsorisé</span>@endif</div>
                        <div class="lo-card-body"><h3><a href="{{ $lien }}">{{ $u->nom }}</a></h3>
                            <p class="lo-location"><x-ui.icone nom="pin" classe="ic" /> {{ $r->commune }}@if($r->quartier) · {{ $r->quartier }}@endif</p>
                            <p class="lo-meta">{{ $u->capacite }} voyageurs · {{ ucfirst($u->type) }}</p>
                            @if($r->aBadgeGestion())<span class="lo-tag">✓ Gérant vérifié</span>@endif
                            <div class="lo-price"><small>{{ $nuitsRecherche === null ? 'Tarif de référence' : 'Montant du séjour' }}</small><b>{{ $fr($nuitsRecherche === null ? $ligne['prix_reference'] : $ligne['total']) }}</b> <span>{{ $nuitsRecherche === null ? '/ nuit' : 'pour '.$nuitsRecherche.' nuit'.($nuitsRecherche > 1 ? 's' : '') }}</span>
                                @if($nuitsRecherche !== null)<em>soit {{ $fr((int) round($ligne['total'] / $nuitsRecherche)) }} / nuit en moyenne</em>@endif</div>
                            <a class="lo-details" href="{{ $lien }}" aria-label="Voir le logement : {{ $u->nom }}">Voir le logement <span aria-hidden="true">→</span></a>
                        </div>
                    </article>
                @empty
                    <div class="lo-empty" style="grid-column:1/-1"><h3>Essayons d’autres critères.</h3>
                        <p>{{ $nuitsRecherche === null ? 'Aucun logement ne correspond à cette sélection. Vous pouvez élargir votre recherche ou retirer un filtre.' : "Aucun logement libre à ces dates. Essayez d'autres dates ou une autre commune." }}</p>
                        <div class="lo-empty-actions">
                            @if($commune !== '')<button class="lo-btn" type="button" wire:click="choisirCommune('')">Toutes les communes</button>@endif
                            @foreach(array_slice($puces, 0, 2) as $p)<button class="lo-btn lo-btn-sec" type="button" wire:click="{{ $p['action'] }}">Retirer « {{ $p['libelle'] }} »</button>@endforeach
                            @if($nuitsRecherche !== null)<button class="lo-btn lo-btn-sec" type="button" @click="document.getElementById('arrivee').focus(); window.scrollTo({ top: 0, behavior: 'smooth' })">Changer les dates</button>@endif
                            @if($commune === '' && ! $puces && $nuitsRecherche === null)<button class="lo-btn" type="button" wire:click="reinitialiser">Élargir ma recherche</button>@endif
                        </div></div>
                @endforelse
            </div>
            <div class="lo-bottom"><p><b>{{ $n }} logement{{ $n > 1 ? 's' : '' }} présenté{{ $n > 1 ? 's' : '' }}</b> · Chaque annonce détaille ses équipements et ses conditions.</p><p>Les offres sponsorisées sont identifiées et présentées en premier.</p></div>
        </section>
    </div>
    <section class="lo-engagements" aria-label="Nos engagements"><div class="lo-cont"><div><b>Un gérant identifié</b><p>Sa pièce d’identité est contrôlée avant publication.</p></div><div><b>Des conditions avant paiement</b><p>Consultez le total et les règles d’annulation avant de confirmer.</p></div><div><b>Des badges précis</b><p>Le badge de gestion correspond à un justificatif vérifié pour la résidence.</p></div></div></section>
</div>
