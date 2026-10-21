@php $fr = fn (int $m) => \App\Noyau\Services\Format::fcfa($m); $u = $r->unite; @endphp
<div style="display:grid;gap:14px">
    <p class="fil"><a href="{{ route('mes-reservations') }}">← Mes réservations</a></p>
    <div class="stepper"><span class="st fait">1 Récapitulatif</span><span class="st on">2 Paiement</span><span class="st">3 Confirmation</span></div>

    @if($modeTest)<x-ui.alerte type="attention"><b>MODE TEST :</b> aucun vrai argent n'est débité.</x-ui.alerte>@endif

    @if(! $enAttente)
        <x-ui.alerte type="erreur">Le délai de garde est terminé : ces dates ne sont plus réservées pour vous. <a class="font-bold underline" href="{{ route('recherche') }}">Refaire une réservation</a>.</x-ui.alerte>
    @else
    <div class="cols">
        <div class="col-g">
            <section class="carte-p"><h1 style="font-size:20px">Votre séjour</h1>
                <div class="res-item"><div style="width:100px;flex:none"><x-ui.photo :photo="$u->photos->first() ?? $u->residence->photos->first()" classe="h-20" :alt="'Photo de '.$u->nom" /></div>
                    <div class="ri"><b>{{ $u->nom }}</b><span class="petit">{{ $u->residence->nom }}, {{ $u->residence->commune }}</span><span>{{ $r->date_arrivee->translatedFormat('j M') }} → {{ $r->date_depart->translatedFormat('j M Y') }} ({{ $r->nombre_nuits ?? $r->date_arrivee->diffInDays($r->date_depart) }} nuits)</span></div></div>
                <div class="ligne"><span>Total du séjour</span><span>{{ $fr($r->montant_total) }}</span></div>
                @if($resteAuGerant > 0)<div class="ligne"><span>À régler au gérant à l'arrivée</span><span>{{ $fr($resteAuGerant) }}</span></div>@endif
            </section>
            <section class="carte-p"><h2>Comment payer ?</h2>
                <div class="pastilles clairs"><span>Wave</span><span>Orange Money</span><span>MTN Money</span><span>Carte bancaire</span></div>
                <p class="petit" style="margin:0">Vous choisissez sur la page sécurisée de GeniusPay. Nous ne voyons jamais votre numéro de carte ni votre code secret.</p>
            </section>
        </div>
        <aside class="col-d">
            <section class="carte-p resa">
                <div class="timer"><x-ui.icone nom="horloge" classe="ic" /><span>Il vous reste <b x-data="{ fin: {{ $r->expire_le->getTimestamp() }}, reste: '' }" x-init="const maj = () => { const s = Math.max(0, this.fin - Math.floor(Date.now() / 1000)); this.reste = Math.floor(s / 3600) + ' h ' + String(Math.floor(s % 3600 / 60)).padStart(2, '0') + ' min'; }; maj(); setInterval(maj, 20000)" x-text="reste">{{ $r->expire_le->diffForHumans(['syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE, 'parts' => 2]) }}</b> pour payer. Passé ce délai, les dates sont libérées.</span></div>
                <h2>À payer maintenant</h2>
                <div class="gros">{{ $fr($r->acompte) }}</div>
                <p class="petit" style="margin:0">{{ $resteAuGerant > 0 ? 'Acompte.' : 'Total du séjour.' }}</p>
                @if($erreur)<x-ui.alerte type="erreur">{{ $erreur }}</x-ui.alerte>@endif
                @if(! $disponible)<x-ui.alerte type="erreur">Le paiement en ligne est momentanément indisponible. Vos dates restent gardées : réessayez plus tard.</x-ui.alerte>@endif
                <button wire:click="payer" wire:loading.attr="disabled" class="btn-pl grand pleine" @disabled(! $disponible)>Payer {{ $fr($r->acompte) }}</button>
                <p class="petit" style="margin:0">Vous quittez Avelis pour la page de paiement sécurisée de GeniusPay, puis vous revenez ici.</p>
                <ul class="garanties"><li><x-ui.icone nom="ok" classe="ic" /> Paiement sécurisé</li></ul>
            </section>
        </aside>
    </div>
    @endif
</div>
