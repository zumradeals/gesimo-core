@php $fr = fn (int $m) => \App\Noyau\Services\Format::fcfa($m); $u = $r->unite; $delai = \App\Paiement\Services\ReglagesPaiement::delaiAnnulationGratuiteHeures(); @endphp
<div class="mr-page ev-page">
    <p class="fil"><a href="{{ route('mes-reservations') }}">Mes réservations</a> › <a href="{{ route('reservation.detail', $r) }}">Réservation n° {{ $r->id }}</a> › Annuler</p>
    <div class="mr-entete"><div><h1>Annuler ma réservation</h1></div></div>
    <div class="ev-cols ev-deux">
        <div class="mr-col">
            <section class="mr-carte">
                <article class="ev-sejour">
                    <div class="mr-photo"><x-ui.photo :photo="$u->photos->first() ?? $u->residence->photos->first()" classe="h-[110px]" :alt="'Photo de '.$u->nom" /></div>
                    <div class="mr-corps"><b style="font-size:18px">{{ $u->nom }} · {{ $u->residence->nom }}</b>
                        <span>{{ $r->date_arrivee->translatedFormat('j M') }} → {{ $r->date_depart->translatedFormat('j M Y') }}</span>
                        @if($apercu)<span class="petit">Déjà payé : {{ $fr($apercu['paye']) }}</span>@endif
                        <span><x-ui.badge-statut :statut="$r->statut" /></span></div>
                </article>
            </section>
            @if(! $annulable)
                <x-ui.alerte type="erreur">Cette réservation ne peut plus être annulée ici.</x-ui.alerte>
            @elseif($apercu['cas'] === 'aucun')
                <x-ui.alerte type="info">Aucun paiement n'a été effectué : l'annulation ne coûte rien et les dates seront libérées.</x-ui.alerte>
            @elseif($apercu['cas'] === 'integral')
                <section class="ev-rembourse"><span class="mr-surtitre">Ce que vous récupérez</span>
                    <div class="gros">Remboursé : {{ $fr($apercu['rembourse']) }}</div>
                    <p style="margin:0">Remboursement intégral, sur le moyen de paiement utilisé.</p>
                    <p class="petit" style="margin:0">Plus de {{ $delai }} h avant l'arrivée · politique flexible.</p></section>
            @else
                <section class="ev-rembourse"><span class="mr-surtitre">Ce que vous récupérez</span>
                    <div class="gros">Remboursé : {{ $fr($apercu['rembourse']) }}</div>
                    <div class="ligne"><span>Moins de {{ $delai }} h avant l'arrivée · une nuit retenue</span><span>{{ $fr($apercu['retenu']) }}</span></div>
                    <p class="petit" style="margin:0">Politique flexible.@if($apercu['retenu'] < $apercu['nuit']) Votre acompte est inférieur au prix d'une nuit ({{ $fr($apercu['nuit']) }}) : il est retenu en entier.@endif</p></section>
            @endif
        </div>
        <div class="mr-col">
            @if(! $annulable)
                <a href="{{ route('reservation.detail', $r) }}" class="btn-pl pleine">Retour à ma réservation</a>
            @else
                <section class="mr-carte">
                    <x-ui.alerte type="attention">Cette annulation est <b>définitive</b>. Les dates seront de nouveau proposées à d'autres voyageurs.</x-ui.alerte>
                    @if($erreur)<x-ui.alerte type="erreur">{{ $erreur }}</x-ui.alerte>@endif
                    <button wire:click="confirmer" wire:loading.attr="disabled" class="btn-pl grand pleine rouge-b">Confirmer l'annulation @if($apercu['rembourse'] > 0)(remboursé : {{ $fr($apercu['rembourse']) }})@endif</button>
                    <a href="{{ route('mes-reservations') }}" class="btn-sec pleine">Garder ma réservation</a>
                </section>
            @endif
        </div>
    </div>
</div>
