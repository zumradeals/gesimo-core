<div class="logements-v2" x-data="filtresLogements">
    <header class="lo-hero"><div class="lo-cont"><div>
        <nav class="lo-fil" aria-label="Fil d’Ariane"><a href="{{ route('accueil') }}">Accueil</a> / <span>Logements</span></nav>
        <span class="lo-eyebrow">Résidences meublées · Côte d’Ivoire</span>
        <h1>Le bon logement,<br>pour votre prochain séjour.</h1>
        <p>Une escapade, un déplacement, quelques jours à Abidjan : trouvez le lieu qui vous correspond.</p>
    </div><div class="lo-ambiance"><img src="{{ asset('images/sejours/salon-balcon.webp') }}" alt="" width="768" height="512"><span>Posez vos valises. Prenez vos repères.</span></div></div></header>
    <div class="lo-cont lo-search-wrap">
        <form wire:submit="chercher" class="lo-search" aria-label="Rechercher un logement">
            <label for="commune"><span>Commune</span><select id="commune" wire:model="commune"><option value="">Toutes les communes</option>@foreach($communes as $c)<option>{{ $c }}</option>@endforeach</select></label>
            <label for="arrivee"><span>Arrivée</span><input id="arrivee" type="date" min="{{ now()->toDateString() }}" wire:model="arrivee"></label>
            <label for="depart"><span>Départ</span><input id="depart" type="date" min="{{ $arrivee ?: now()->toDateString() }}" wire:model="depart"></label>
            <label for="personnes"><span>Voyageurs</span><input id="personnes" type="number" min="1" max="50" wire:model="personnes" inputmode="numeric"></label>
            <button type="submit" class="lo-btn" data-reseau wire:loading.attr="disabled"><x-ui.icone nom="recherche" classe="ic" /> Rechercher</button>
        </form>
        @if($erreur || $errors->any())<div class="lo-error" role="alert">@if($erreur)<p>{{ $erreur }}</p>@endif @foreach($errors->all() as $message)<p>{{ $message }}</p>@endforeach</div>@endif
    </div>
    <div class="lo-cont lo-main" id="catalogue">
        <aside class="lo-side">
            <dialog x-ref="panel" wire:ignore.self class="lo-panel" aria-labelledby="filtres-titre" @close="ferme()" @cancel="ferme()">
                <div class="lo-panel-title"><h2 id="filtres-titre">Affiner votre séjour</h2><button type="button" class="lo-close" @click="fermer()" aria-label="Fermer les filtres">×</button></div>
                <fieldset class="lo-group"><legend>Budget du séjour</legend>
                    <div class="lo-budget"><input id="budget" type="number" min="0" step="1" wire:model.live.debounce.400ms="budget" placeholder="Montant maximum" aria-label="Budget maximum du séjour" aria-describedby="aide-budget" @disabled($nuits === null)><span>FCFA</span></div>
                    <p id="aide-budget">{{ $nuits === null ? 'Choisissez vos dates pour filtrer sur le montant total.' : 'Le montant total, pour toutes les nuits et tous les voyageurs.' }}</p>
                </fieldset>
                <fieldset class="lo-group"><legend>Les équipements essentiels</legend>
                    @foreach($listeEquipements as $e)<label class="lo-check" wire:key="equipement-{{ $e->id }}"><input type="checkbox" wire:model.live="equipements" value="{{ $e->id }}">{{ $e->libelle }}</label>@endforeach
                </fieldset>
                <div style="padding-top:18px"><button class="lo-reset" type="button" wire:click="reinitialiser">Réinitialiser les filtres ↺</button></div>
                <button class="lo-btn lo-apply" type="button" @click="fermer()">Afficher les logements</button>
            </dialog>
            <div class="lo-help"><b>Besoin d’un repère ?</b><p>Notre équipe peut vous expliquer le fonctionnement de la réservation.</p><a href="{{ route('faq') }}">Comprendre avant de réserver ↗</a></div>
        </aside>
        <section class="lo-results" aria-label="Catalogue de logements" aria-live="polite" aria-atomic="false">
            <div class="lo-result-top">
                <div><h2>{{ $nuits === null ? 'Des adresses à découvrir' : 'Votre prochain séjour commence ici' }}</h2>
                    <p>{{ $resultats->count() }} logement{{ $resultats->count() > 1 ? 's' : '' }} {{ $nuits === null ? 'présenté'.($resultats->count() > 1 ? 's' : '') : 'libre'.($resultats->count() > 1 ? 's' : '').' pour '.$nuits.' nuit'.($nuits > 1 ? 's' : '') }}@if($commune) · {{ $commune }}@endif</p>
                </div>
                <button x-ref="ouvrir" type="button" class="lo-filter-button" @click="ouvrir()" aria-controls="filtres-titre" :aria-expanded="ouvert.toString()">☷ Filtres @if(count($equipements) || ($nuits !== null && $budget !== ''))<span>({{ count($equipements) + (int) ($nuits !== null && $budget !== '') }})</span>@endif</button>
                <label class="lo-sort"><span>Trier par</span><select wire:model.live="tri" aria-label="Trier les logements"><option value="recommande">Prix croissant</option><option value="prix_desc">Prix décroissant</option></select></label>
            </div>
            <div class="lo-note"><x-ui.icone nom="calendrier" classe="ic" /><span>{{ $nuits === null ? 'Indiquez vos dates pour connaître les disponibilités et le prix total du séjour. Les tarifs par nuit sont donnés à titre de référence.' : 'Ces logements sont libres à vos dates. Les prix affichés correspondent au montant total du séjour.' }}</span></div>
            <div class="lo-grid" wire:loading.class="opacity-60">
                @forelse($resultats as $ligne)
                    @php
                        $u = $ligne['unite']; $r = $u->residence;
                        $lien = route('logement', ['unite' => $u->id] + ($nuits !== null ? ['arrivee' => $arrivee, 'depart' => $depart, 'personnes' => $personnes] : ['personnes' => $personnes]));
                    @endphp
                    <article class="lo-card" wire:key="logement-{{ $u->id }}" data-logement="{{ $u->id }}">
                        <div class="lo-photo"><a href="{{ $lien }}" tabindex="-1" aria-hidden="true"><x-ui.photo :photo="$u->photos->first() ?? $r->photos->first()" classe="h-full" :plat="true" :alt="'Photo de '.$u->nom" sizes="(max-width: 520px) 92vw, (max-width: 1150px) 40vw, 30vw" /></a>@if($ligne['sponsorise'])<span class="lo-sponsored">Sponsorisé</span>@endif</div>
                        <div class="lo-card-body"><h3><a href="{{ $lien }}">{{ $u->nom }}</a></h3>
                            <p class="lo-location"><x-ui.icone nom="pin" classe="ic" /> {{ $r->commune }}@if($r->quartier) · {{ $r->quartier }}@endif</p>
                            <p class="lo-meta">{{ $u->capacite }} voyageurs · {{ ucfirst($u->type) }}</p>
                            @if($r->aBadgeGestion())<span class="lo-tag">✓ Gestion vérifiée</span>@endif
                            <div class="lo-price"><small>{{ $nuits === null ? 'Tarif de référence' : 'Montant du séjour' }}</small><b>{{ \App\Noyau\Services\Format::fcfa($nuits === null ? $ligne['prix_reference'] : $ligne['total']) }}</b> <span>{{ $nuits === null ? '/ nuit' : 'pour '.$nuits.' nuit'.($nuits > 1 ? 's' : '') }}</span></div>
                            <a class="lo-details" href="{{ $lien }}" aria-label="Voir le logement : {{ $u->nom }}">Voir le logement <span aria-hidden="true">→</span></a>
                        </div>
                    </article>
                @empty
                    <div class="lo-empty" style="grid-column:1/-1"><h3>Essayons d’autres critères.</h3><p>{{ $nuits === null ? 'Aucun logement ne correspond à cette sélection. Vous pouvez élargir votre recherche ou retirer un équipement.' : "Aucun logement libre à ces dates. Essayez d'autres dates ou une autre commune." }}</p><button class="lo-btn" type="button" wire:click="reinitialiser">Élargir ma recherche</button></div>
                @endforelse
            </div>
            <div class="lo-bottom"><p><b>{{ $resultats->count() }} logements présentés</b><br>Chaque annonce détaille ses équipements et ses conditions.</p><p>Les offres sponsorisées sont identifiées<br>et présentées en premier.</p></div>
        </section>
    </div>
    <section class="lo-engagements" aria-label="Nos engagements"><div class="lo-cont"><div><b>Un gérant identifié</b><p>Sa pièce d’identité est contrôlée avant publication.</p></div><div><b>Des conditions avant paiement</b><p>Consultez le total et les règles d’annulation avant de confirmer.</p></div><div><b>Des badges précis</b><p>Le badge de gestion correspond à un justificatif vérifié pour la résidence.</p></div></div></section>
</div>
