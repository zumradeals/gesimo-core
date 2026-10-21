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
            @if($r->aBadgeGestion())<span class="etq vert">✔ Droit de gestion vérifié</span>@endif
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

            <aside class="resa" id="resa">
                <div class="resa-p">
                    @if($total !== null)<b>{{ $fr($total) }}</b> <small>pour {{ count($detail) }} nuit{{ count($detail) > 1 ? 's' : '' }}</small>
                    @else<small>Choisissez vos dates pour voir le prix total du séjour.</small>@endif
                </div>
                <div class="duo">
                    <div class="champ"><label class="etiquette" for="arrivee">Arrivée</label><input id="arrivee" type="date" min="{{ now()->toDateString() }}" wire:model.live="arrivee" class="saisie"></div>
                    <div class="champ"><label class="etiquette" for="depart">Départ</label><input id="depart" type="date" min="{{ $arrivee ?: now()->toDateString() }}" wire:model.live="depart" class="saisie"></div>
                </div>
                <div class="champ"><label class="etiquette" for="personnes">Personnes</label><input id="personnes" type="number" min="1" max="{{ $u->capacite }}" wire:model.live="personnes" class="saisie"></div>

                @if($erreur) <x-ui.alerte type="erreur">{{ $erreur }}</x-ui.alerte>
                @elseif($detail === null && $arrivee !== '' && $depart !== '') <x-ui.alerte type="attention">Ce logement n'a pas encore de prix.</x-ui.alerte>
                @elseif($detail !== null)
                    <div>
                        @foreach($detail as $nuit)
                            <div class="ligne"><span>{{ $nuit['date']->translatedFormat('D j M') }}@if($nuit['type'] === 'week_end') <span class="petit">(week-end)</span>@elseif($nuit['type'] === 'fete') <span class="petit">(période spéciale)</span>@endif</span><span>{{ $fr($nuit['prix']) }}</span></div>
                        @endforeach
                        <div class="ligne" style="font-weight:800"><span>Total, sans frais cachés</span><span>{{ $fr($total) }}</span></div>
                        @if($acompte !== null)
                            <p class="petit" style="margin-top:6px">Acompte à payer en ligne : {{ $fr($acompte) }}. Le reste ({{ $fr($solde) }}) se règle au gérant à l'arrivée.</p>
                        @else
                            <p class="petit" style="margin-top:6px">Paiement en ligne de la totalité du séjour.</p>
                        @endif
                    </div>
                    @if($libre)
                        <a href="{{ $lienReserver }}" data-reseau class="btn-pl grand">Réserver</a>
                    @else
                        <x-ui.alerte type="erreur">Ces dates ne sont plus libres. Choisissez d'autres dates.</x-ui.alerte>
                    @endif
                @endif
                <ul class="garanties">
                    <li><x-ui.icone nom="bouclier" classe="ic" /> Résidence vérifiée</li>
                    <li><x-ui.icone nom="carte" classe="ic" /> Paiement sécurisé</li>
                    <li><x-ui.icone nom="retour" classe="ic" /> Annulation gratuite jusqu'à {{ $delai }} h avant</li>
                </ul>
            </aside>
        </div>
    </div>

    {{-- Téléphone : barre de réservation fixe --}}
    <div class="barre-resa">
        @if($total !== null && $libre)
            <div><b>{{ $fr($total) }}</b><br><small>{{ count($detail) }} nuit{{ count($detail) > 1 ? 's' : '' }}</small></div>
            <a href="{{ $lienReserver }}" data-reseau class="btn-pl">Réserver</a>
        @else
            <div><small>Choisissez vos dates</small></div>
            <a href="#resa" class="btn-pl">Voir les dates</a>
        @endif
    </div>
</div>
