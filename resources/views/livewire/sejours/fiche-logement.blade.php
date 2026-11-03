@php
    $r = $u->residence;
    $photos = ($u->photos->isNotEmpty() ? $u->photos : $r->photos)->take(5);
    $fr = fn (int $m) => \App\Noyau\Services\Format::fcfa($m);
    $acompte = null;
    if ($total !== null && $r->mode_paiement === 'acompte' && \App\Paiement\Services\PlancherAcompte::acompteEstPossible()) {
        [$acompte, $solde] = app(\App\Sejours\Services\ReservationService::class)->repartition($u, $total);
    }
    $delai = \App\Parametres\Services\Reglages::entier('annulation.delai_gratuit_heures');
    $lienReserver = route('reserver', ['unite' => $u->id, 'arrivee' => $arrivee, 'depart' => $depart, 'personnes' => $personnes]);
@endphp
<div>
    <div class="cont fiche-cont">
        <p class="fil"><a href="{{ route('accueil') }}">Accueil</a> › <a href="{{ route('recherche') }}">Logements</a> › {{ $r->commune }} › {{ $u->nom }}</p>
        <h1 class="h1-fiche">{{ $u->nom }}
            @if($r->aBadgeGestion())<span class="etq vert">✔ Gérant vérifié</span>@endif
            @if($sponsorise)<span class="etq" style="background:var(--or);color:#3a2a00">★ Sponsorisé</span>@endif
        </h1>
        <p class="aide-l">{{ $r->nom }} · {{ $r->commune }}@if($r->quartier), {{ $r->quartier }}@endif · jusqu'à {{ $u->capacite }} personne{{ $u->capacite > 1 ? 's' : '' }}
            @if($resumeAvis['nombre'] > 0) · <x-ui.etoiles :note="$resumeAvis['moyenne']" /> {{ number_format($resumeAvis['moyenne'], 1, ',', '') }} ({{ $resumeAvis['nombre'] }}) @endif</p>

        @if($r->gerant->profilGerant && $r->estPubliee())<p class="aide-l">Proposé par <a class="font-bold text-marque-600 underline" href="{{ route('hebergeur', $r->gerant->profilGerant->slug) }}">{{ $r->gerant->profilGerant->nom_public }} · Voir ses autres offres →</a>@if($r->gerant->profilGerant->estCertifie()) <span class="pg-badge or">✓ Gérant certifié</span>@endif</p>@endif
        <div class="galerie {{ $photos->count() < 2 ? 'seule' : '' }}">
            @forelse($photos as $p)
                <div><img src="{{ $p->url() }}" alt="Photo {{ $loop->iteration }} de {{ $u->nom }}" width="1600" height="1067" decoding="async" @if($loop->first) fetchpriority="high" @else loading="lazy" @endif></div>
            @empty
                <div style="display:flex;align-items:center;justify-content:center"><x-ui.icone nom="photo" classe="ic gros" /></div>
            @endforelse
        </div>

        <div class="fiche-grille">
            <div>
                <div class="bloc"><h2>Équipements</h2>
                    <div class="equip">@forelse($u->equipements as $e)<span><x-ui.icone nom="ok" classe="ic pt" /> {{ $e->libelle }}</span>@empty<span class="petit">Non précisés</span>@endforelse</div></div>
                <div class="bloc"><h2>Règlement</h2><p style="margin:0;color:var(--gris)">{{ $r->reglement ?: 'Aucun règlement particulier.' }}</p></div>
                <div class="bloc"><h2>Annulation</h2>
                    @if($r->politique_annulation === 'flexible')
                        <p style="margin:0;color:var(--gris)">Remboursement intégral jusqu'à {{ $delai }} h avant l'arrivée. Moins de {{ $delai }} h avant : une nuit retenue, le reste remboursé. Absence sans annulation : aucun remboursement.</p>
                    @else
                        <p style="margin:0;color:var(--gris)">Politique plus stricte choisie par le gérant. Les conditions précises vous sont affichées avant le paiement.</p>
                    @endif
                </div>
                <div class="bloc avis-l"><h2>Avis @if($resumeAvis['nombre'] > 0)<small style="font-weight:400;color:var(--gris)">({{ number_format($resumeAvis['moyenne'], 1, ',', '') }} / 5 · {{ $resumeAvis['nombre'] }} séjour{{ $resumeAvis['nombre'] > 1 ? 's' : '' }})</small>@endif</h2>
                    @forelse($avis as $a)
                        <div style="border-bottom:1px dashed var(--bord);padding:8px 0"><b>{{ \Illuminate\Support\Str::of($a->client->nom)->before(' ') }}</b> <x-ui.etoiles :note="$a->note" /> <span class="petit">{{ $a->created_at->translatedFormat('M Y') }}</span>@if($a->commentaire)<p>« {{ $a->commentaire }} »</p>@endif</div>
                    @empty
                        <p>Pas encore d'avis pour cette résidence.</p>
                    @endforelse</div>
                <div class="bloc"><h2>Où ?</h2>
                    <p style="margin:0;color:var(--gris)">{{ $r->commune }}@if($r->quartier), {{ $r->quartier }}@endif. L'adresse exacte, la position GPS et le contact du gérant vous sont donnés après la confirmation de votre réservation.</p></div>
            </div>

            <aside class="resa rv-encadre" id="resa" aria-label="Réserver ce logement">
                <div class="resa-p">
                    @if($prixNuit !== null)<b>{{ $fr($prixNuit) }}</b> <small>par nuit (tarif de référence)</small>
                    @else<small>Ce logement n'a pas encore de prix.</small>@endif
                </div>

                <div class="champ"><label class="etiquette" for="arrivee">Date d'arrivée</label>
                    <input id="arrivee" type="date" min="{{ now()->toDateString() }}" wire:model.live="arrivee" class="saisie">
                    <small class="petit">Arrivée à partir de {{ $heureArrivee }}</small></div>

                <div class="champ"><span class="etiquette" id="nuits-lib">Combien de nuits ?</span>
                    <div class="rv-compteur" role="group" aria-labelledby="nuits-lib">
                        <button type="button" wire:click="moinsNuit" @disabled($nuits <= $nuitsMin) aria-label="Une nuit de moins">−</button>
                        <output aria-live="polite"><b>{{ $nuits }}</b><small>nuit{{ $nuits > 1 ? 's' : '' }}</small></output>
                        <button type="button" wire:click="plusNuit" @disabled($nuits >= $nuitsMax) aria-label="Une nuit de plus">+</button>
                    </div>
                    <div class="rv-puces">
                        @foreach([1 => '1 nuit', 2 => 'Week-end · 2', 7 => '1 semaine', 14 => '2 semaines', 30 => '1 mois'] as $n => $lib)
                            @if($n >= $nuitsMin && $n <= $nuitsMax)
                                <button type="button" wire:click="fixerNuits({{ $n }})" class="{{ $nuits === $n ? 'on' : '' }}" aria-pressed="{{ $nuits === $n ? 'true' : 'false' }}">{{ $lib }}</button>
                            @endif
                        @endforeach
                    </div>
                    @if($nuitsMin > 1)<small class="petit">Séjour minimum : {{ $nuitsMin }} nuits.</small>@endif
                </div>

                <div class="rv-depart"><span>Départ</span>
                    <b>@if($dateDepart){{ $dateDepart->translatedFormat('D j M Y') }}@else —@endif</b>
                    <small>avant {{ $heureDepart }}</small></div>

                <div class="champ"><span class="etiquette" id="pers-lib">Voyageurs <small class="petit">({{ $u->capacite }} au maximum)</small></span>
                    <div class="rv-compteur" role="group" aria-labelledby="pers-lib">
                        <button type="button" wire:click="moinsPersonne" @disabled($personnes <= 1) aria-label="Un voyageur de moins">−</button>
                        <output aria-live="polite"><b>{{ $personnes }}</b><small>personne{{ $personnes > 1 ? 's' : '' }}</small></output>
                        <button type="button" wire:click="plusPersonne" @disabled($personnes >= $u->capacite) aria-label="Un voyageur de plus">+</button>
                    </div>
                </div>

                @if($erreur) <x-ui.alerte type="erreur">{{ $erreur }}</x-ui.alerte>
                @elseif($tropLong) <x-ui.alerte type="attention">Au-delà de {{ $nuitsMax }} nuits, la réservation en ligne n'est pas possible. <a class="font-bold underline" href="{{ route('contact') }}">Contactez-nous</a> pour organiser un long séjour.</x-ui.alerte>
                @elseif($tropCourt) <x-ui.alerte type="attention">Le séjour minimum est de {{ $nuitsMin }} nuit{{ $nuitsMin > 1 ? 's' : '' }}.</x-ui.alerte>
                @elseif($arrivee === '') <p class="petit" style="margin:0">Choisissez votre date d'arrivée pour voir le total du séjour.</p>
                @elseif($detail === null) <x-ui.alerte type="attention">Ce logement n'a pas encore de prix.</x-ui.alerte>
                @else
                    <div class="rv-prix-detail">
                        @foreach($groupes as $g)
                            <div class="ligne"><span>{{ $g['nuits'] }} nuit{{ $g['nuits'] > 1 ? 's' : '' }} × {{ $fr($g['prix']) }}@if($g['type'] === 'week_end') <span class="petit">(week-end)</span>@elseif($g['type'] === 'fete') <span class="petit">(période spéciale)</span>@endif</span><span>{{ $fr($g['nuits'] * $g['prix']) }}</span></div>
                        @endforeach
                        <div class="ligne total"><span>Total, sans frais cachés</span><span>{{ $fr($total) }}</span></div>
                        @if(count($groupes) > 1 || $nuits > 1)
                            <details class="rv-nuits"><summary>Voir le prix nuit par nuit</summary>
                                @foreach($detail as $nuit)
                                    <div class="ligne"><span>{{ $nuit['date']->translatedFormat('D j M') }}</span><span>{{ $fr($nuit['prix']) }}</span></div>
                                @endforeach
                            </details>
                        @endif
                        @if($acompte !== null)
                            <p class="petit" style="margin-top:6px">Acompte à payer en ligne : {{ $fr($acompte) }}. Le reste ({{ $fr($solde) }}) se règle au gérant à l'arrivée.</p>
                        @else
                            <p class="petit" style="margin-top:6px">Paiement en ligne de la totalité du séjour.</p>
                        @endif
                    </div>
                    @if($libre)
                        <p class="petit" style="margin:0">Vous ne payez rien maintenant : vos dates sont gardées à l'étape suivante.</p>
                        <a href="{{ $lienReserver }}" data-reseau class="btn-pl grand">Réserver</a>
                    @else
                        <x-ui.alerte type="erreur"><b>Ces dates ne sont plus libres.</b>@if($suggestions !== []) Voici les dates libres les plus proches pour {{ $nuits }} nuit{{ $nuits > 1 ? 's' : '' }} :@else Choisissez d'autres dates.@endif</x-ui.alerte>
                        @if($suggestions !== [])
                            <div class="rv-suggestions">
                                @foreach($suggestions as $sug)
                                    <button type="button" wire:click="choisirArrivee('{{ $sug['arrivee']->toDateString() }}')">Du {{ $sug['arrivee']->translatedFormat('j M') }} au {{ $sug['depart']->translatedFormat('j M Y') }}<span class="petit">Choisir</span></button>
                                @endforeach
                            </div>
                        @endif
                    @endif
                @endif
                <ul class="garanties">
                    <li><x-ui.icone nom="bouclier" classe="ic" /> Résidence vérifiée</li>
                    <li><x-ui.icone nom="carte" classe="ic" /> Paiement sécurisé (Wave, Orange Money, MTN Money, carte)</li>
                    <li><x-ui.icone nom="retour" classe="ic" />
                        @if($r->politique_annulation !== 'flexible') Conditions d'annulation précisées avant le paiement
                        @elseif($limiteAnnulation === null) Annulation gratuite jusqu'à {{ $delai }} h avant l'arrivée
                        @elseif($limiteAnnulation->lt(now())) Annulation gratuite : délai dépassé pour ces dates
                        @else Annulation gratuite jusqu'au {{ $limiteAnnulation->translatedFormat('j M') }} à {{ \App\Sejours\Services\ReglesSejour::heureLisible($limiteAnnulation->format('H:i')) }}
                        @endif</li>
                </ul>
            </aside>
        </div>
    </div>

    {{-- Téléphone : barre de réservation fixe --}}
    <div class="barre-resa">
        @if($total !== null && $libre)
            <div><b>{{ $fr($total) }}</b><br><small>{{ $nuits }} nuit{{ $nuits > 1 ? 's' : '' }}</small></div>
            <a href="{{ $lienReserver }}" data-reseau class="btn-pl">Réserver</a>
        @else
            <div><small>Choisissez vos dates</small></div>
            <a href="#resa" class="btn-pl">Voir les dates</a>
        @endif
    </div>
</div>
