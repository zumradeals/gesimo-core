@php $fr = fn (int $m) => \App\Noyau\Services\Format::fcfa($m); $u = $r->unite; $res = $u->residence; @endphp
<div class="rv-page" style="display:grid;gap:14px">
    <x-site.etapes-reservation :etape="3" />
    <section class="carte-p succes-b">
        <div class="gros-ok"><x-ui.icone nom="ok" classe="ic" /></div>
        <h1 style="font-size:26px">Votre réservation est confirmée</h1>
        <p style="margin:0">Réservation n° <b>{{ $r->id }}</b> · un e-mail de confirmation est envoyé à {{ auth()->user()->email }}</p>
        @if($tardif)<x-ui.alerte type="info">Votre paiement est arrivé après le délai de garde, mais les dates étaient encore libres : tout est en ordre.</x-ui.alerte>@endif
    </section>
    <div class="cols">
        <div class="col-g">
            @if(! $pieceFournie)
                <section class="carte-p rv-a-faire" id="piece-identite"><span class="surtitre">À faire avant votre arrivée</span>
                    <h2>Ajoutez votre pièce d'identité</h2>
                    <p style="margin:0">Le gérant en a besoin pour vous accueillir. C'est rapide, et vous ne le ferez qu'une fois : elle sera gardée dans votre profil pour vos prochains séjours.</p>
                    @livewire(\App\Sejours\Livewire\PieceIdentite::class, ['source' => 'confirmation'])
                </section>
            @else
                <section class="carte-p"><h2>Pièce d'identité</h2><p style="margin:0"><b>✓ Votre pièce d'identité est déjà enregistrée</b> dans votre profil. Présentez la même pièce, en original, le jour de l'arrivée.</p></section>
            @endif
            <section class="carte-p"><h2>Prochaines étapes</h2>
                <ul class="rv-liste">
                    <li>{{ $pieceFournie ? '✓ Pièce d\'identité fournie' : 'Ajoutez votre pièce d\'identité (ci-dessus)' }}</li>
                    <li>Vous recevez un rappel la veille de votre arrivée</li>
                    <li>Le jour J : présentez-vous avec la même pièce, en original</li>
                </ul>
            </section>
            <section class="carte-p"><h2>Votre séjour</h2>
                <div class="res-item"><div style="width:100px;flex:none"><x-ui.photo :photo="$u->photos->first() ?? $res->photos->first()" classe="h-20" :alt="'Photo de '.$u->nom" /></div>
                    <div class="ri"><b>{{ $u->nom }}</b><span class="petit">{{ $res->nom }} · {{ $res->commune }}@if($res->quartier), {{ $res->quartier }}@endif</span></div></div>
                <div class="ligne"><span>Arrivée</span><span>{{ $r->date_arrivee->translatedFormat('D j M Y') }}</span></div>
                <div class="ligne"><span>Départ</span><span>{{ $r->date_depart->translatedFormat('D j M Y') }}@if($r->heure_depart) <small class="petit">avant {{ \App\Sejours\Services\ReglesSejour::heureLisible($r->heure_depart) }}</small>@endif</span></div>
                <div class="ligne"><span>Déjà payé en ligne</span><b style="color:#116b2b">{{ $fr($paiement->montant) }}</b></div>
                @if($r->solde > 0)<div class="ligne"><span>Reste à payer au gérant à l'arrivée</span><b>{{ $fr($r->solde) }}</b></div>@endif
            </section>
            <section class="carte-p"><h2>Contact du gérant</h2>
                <div class="coord"><div><x-ui.icone nom="compte" classe="ic" /><span>{{ $res->gerant->nom }}</span></div><div><x-ui.icone nom="tel" classe="ic" /><span>{{ \App\Noyau\Services\Telephone::afficher($res->gerant->telephone) }}</span></div>
                    @if($res->latitude !== null)<div><x-ui.icone nom="pin" classe="ic" /><span><a target="_blank" rel="noopener" href="https://www.google.com/maps?q={{ $res->latitude }},{{ $res->longitude }}">Voir sur la carte</a></span></div>@endif</div>
                @if($res->reglement)<p class="petit" style="margin:0">Règlement : {{ $res->reglement }}</p>@endif
            </section>
        </div>
        <aside class="col-d"><section class="carte-p resa">
            <a href="{{ route('reservation.recu', $r) }}" target="_blank" class="btn-pl pleine">Télécharger mon reçu</a>
            <a href="{{ route('mes-reservations') }}" class="btn-sec pleine">Mes réservations</a>
        </section></aside>
    </div>
</div>
