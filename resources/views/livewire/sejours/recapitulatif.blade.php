@php
    $fr = fn (int $m) => \App\Noyau\Services\Format::fcfa($m);
    $r = $u->residence;
    $duree = $dureeMinutes % 60 === 0 ? ($dureeMinutes / 60).' heure'.($dureeMinutes > 60 ? 's' : '') : $dureeMinutes.' minutes';
@endphp
<div style="display:grid;gap:14px">
    <p class="fil"><a href="{{ route('logement', ['unite' => $u->id, 'arrivee' => $arrivee, 'depart' => $depart, 'personnes' => $personnes]) }}">← Modifier mon séjour</a></p>
    <div class="stepper"><span class="st on">1 Récapitulatif</span><span class="st">2 Paiement</span><span class="st">3 Confirmation</span></div>

    @if(! $valide)
        <x-ui.alerte type="erreur">Les dates ne sont pas valides. Revenez à la fiche du logement pour les choisir.</x-ui.alerte>
    @elseif($total === null)
        <x-ui.alerte type="attention">Ce logement n'a pas encore de prix.</x-ui.alerte>
    @else
        <div class="cols">
            <div class="col-g">
                <section class="carte-p"><h1 style="font-size:20px">Votre séjour</h1>
                    <div class="res-item"><div style="width:100px;flex:none"><x-ui.photo :photo="$u->photos->first() ?? $r->photos->first()" classe="h-20" :alt="'Photo de '.$u->nom" /></div>
                        <div class="ri"><b>{{ $u->nom }}</b><span class="petit">{{ $r->nom }}, {{ $r->commune }}</span></div></div>
                    <div class="ligne"><span>Arrivée</span><span>{{ \Carbon\CarbonImmutable::parse($arrivee)->translatedFormat('D j M Y') }}</span></div>
                    <div class="ligne"><span>Départ</span><span>{{ \Carbon\CarbonImmutable::parse($depart)->translatedFormat('D j M Y') }}</span></div>
                    <div class="ligne"><span>Nuits / personnes</span><span>{{ $nuits }} / {{ $personnes }}</span></div>
                </section>
                <section class="carte-p"><h2>Prix</h2>
                    <div class="ligne"><b>Total du séjour</b><b>{{ $fr($total) }}</b></div>
                    <p class="petit" style="margin:0">Aucun autre frais.</p>
                </section>
            </div>
            <aside class="col-d">
                <section class="carte-p resa">
                    <div class="timer"><x-ui.icone nom="horloge" classe="ic" /><span>En touchant le bouton, ces dates sont gardées pour vous pendant <b>{{ $duree }}</b>. Sans confirmation dans ce délai, elles sont libérées.</span></div>
                    <h2>{{ $solde > 0 ? 'À payer en ligne (acompte)' : 'À payer en ligne' }}</h2>
                    <div class="gros">{{ $fr($acompte) }}</div>
                    @if($solde > 0)<p class="petit" style="margin:0">À payer au gérant à l'arrivée : {{ $fr($solde) }}</p>@endif
                    @if($erreur) <x-ui.alerte type="erreur">{{ $erreur }}</x-ui.alerte> @endif
                    @if(! $libre && ! $erreur) <x-ui.alerte type="erreur">Ces dates ne sont plus libres. Choisissez d'autres dates.</x-ui.alerte> @endif
                    <button wire:click="garder" wire:loading.attr="disabled" class="btn-pl grand pleine" @disabled(! $libre)>Garder ces dates {{ $duree }}</button>
                    <p class="petit" style="margin:0">Ensuite, vous payez {{ $fr($acompte) }} en ligne (Wave, Orange Money, MTN Money ou carte).</p>
                </section>
            </aside>
        </div>
    @endif
</div>
