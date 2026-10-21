@php $fr = fn (int $m) => \App\Noyau\Services\Format::fcfa($m); $u = $r->unite; $delai = \App\Paiement\Services\ReglagesPaiement::delaiAnnulationGratuiteHeures(); @endphp
<div style="display:grid;gap:14px">
    <p class="fil"><a href="{{ route('mes-reservations') }}">Mes réservations</a> › <a href="{{ route('reservation.detail', $r) }}">Réservation n° {{ $r->id }}</a> › Annuler</p>
    <section class="carte-p" style="max-width:760px">
        <h1 style="font-size:24px">Annuler ma réservation</h1>
        <div class="res-item"><div style="width:100px;flex:none"><x-ui.photo :photo="$u->photos->first() ?? $u->residence->photos->first()" classe="h-20" :alt="'Photo de '.$u->nom" /></div>
            <div class="ri"><span><x-ui.badge-statut :statut="$r->statut" /></span><b>{{ $u->nom }} · {{ $u->residence->nom }}</b><span class="petit">{{ $r->date_arrivee->translatedFormat('j M') }} → {{ $r->date_depart->translatedFormat('j M Y') }}@if($apercu) · déjà payé : {{ $fr($apercu['paye']) }}@endif</span></div></div>

        @if(! $annulable)
            <x-ui.alerte type="erreur">Cette réservation ne peut plus être annulée ici.</x-ui.alerte>
        @else
            @if($apercu['cas'] === 'aucun')
                <x-ui.alerte type="info">Aucun paiement n'a été effectué : l'annulation ne coûte rien et les dates seront libérées.</x-ui.alerte>
            @elseif($apercu['cas'] === 'integral')
                <div class="encart"><b>Ce que vous récupérez</b>
                    <div class="ligne"><span>Plus de {{ $delai }} h avant l'arrivée</span><span>Politique flexible</span></div>
                    <div class="ligne"><b>Remboursé : {{ $fr($apercu['rembourse']) }}</b><span class="petit">sur le moyen de paiement utilisé</span></div>
                    <p class="petit" style="margin:0">Remboursement intégral.</p></div>
            @else
                <div class="encart"><b>Ce que vous récupérez</b>
                    <div class="ligne"><span>Moins de {{ $delai }} h avant l'arrivée</span><span>Politique flexible</span></div>
                    <div class="ligne"><span>Une nuit retenue</span><span>{{ $fr($apercu['retenu']) }}</span></div>
                    <div class="ligne"><b>Remboursé : {{ $fr($apercu['rembourse']) }}</b></div>
                    @if($apercu['retenu'] < $apercu['nuit'])<p class="petit" style="margin:0">Votre acompte est inférieur au prix d'une nuit ({{ $fr($apercu['nuit']) }}) : il est retenu en entier.</p>@endif</div>
            @endif

            <x-ui.alerte type="attention">Cette annulation est <b>définitive</b>. Les dates seront de nouveau proposées à d'autres voyageurs.</x-ui.alerte>
            @if($erreur)<x-ui.alerte type="erreur">{{ $erreur }}</x-ui.alerte>@endif
            <button wire:click="confirmer" wire:loading.attr="disabled" class="btn-pl grand pleine rouge-b">Confirmer l'annulation @if($apercu['rembourse'] > 0)(remboursé : {{ $fr($apercu['rembourse']) }})@endif</button>
            <a href="{{ route('mes-reservations') }}" class="btn-sec pleine">Garder ma réservation</a>
        @endif
    </section>
</div>
