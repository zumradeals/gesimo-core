@php $fr = fn (int $m) => \App\Noyau\Services\Format::fcfa($m); $u = $r->unite; @endphp
<div class="mx-auto grid max-w-3xl gap-4">
    <a href="{{ route('mes-reservations') }}" class="inline-flex items-center gap-1 font-semibold text-marque-600"><x-ui.icone nom="fleche-gauche" classe="h-5 w-5" /> Mes réservations</a>
    <h1 class="text-2xl font-extrabold">Annuler ma réservation</h1>

    <div class="carte grid gap-1">
        <x-ui.badge-statut :statut="$r->statut" />
        <h2 class="text-lg font-extrabold">{{ $u->nom }} · {{ $u->residence->nom }}</h2>
        <p class="aide">{{ $r->date_arrivee->translatedFormat('j M') }} → {{ $r->date_depart->translatedFormat('j M Y') }}@if($apercu) · déjà payé : {{ $fr($apercu['paye']) }}@endif</p>
    </div>

    @if(! $annulable)
        <x-ui.alerte type="erreur">Cette réservation ne peut plus être annulée ici.</x-ui.alerte>
    @else
        @if($apercu['cas'] === 'aucun')
            <x-ui.alerte type="info">Aucun paiement n'a été effectué : l'annulation ne coûte rien et les dates seront libérées.</x-ui.alerte>
        @elseif($apercu['cas'] === 'integral')
            <div class="carte border-green-300"><span class="rounded-full bg-green-100 px-3 py-0.5 text-[13px] font-bold text-green-800">Plus de {{ \App\Paiement\Services\ReglagesPaiement::delaiAnnulationGratuiteHeures() }} h avant l'arrivée</span>
                <p class="mt-2 text-2xl font-extrabold">Remboursé : {{ $fr($apercu['rembourse']) }}</p>
                <p class="aide">Remboursement intégral, sur le moyen de paiement utilisé.</p></div>
        @else
            <div class="carte border-amber-300"><span class="rounded-full bg-amber-100 px-3 py-0.5 text-[13px] font-bold text-amber-800">Moins de {{ \App\Paiement\Services\ReglagesPaiement::delaiAnnulationGratuiteHeures() }} h avant l'arrivée</span>
                <div class="mt-2 flex justify-between"><span>Une nuit retenue</span><span>{{ $fr($apercu['retenu']) }}</span></div>
                <p class="text-2xl font-extrabold">Remboursé : {{ $fr($apercu['rembourse']) }}</p>
                @if($apercu['retenu'] < $apercu['nuit'])<p class="aide">Votre acompte est inférieur au prix d'une nuit ({{ $fr($apercu['nuit']) }}) : il est retenu en entier.</p>@endif</div>
        @endif

        <x-ui.alerte type="attention">Cette annulation est <b>définitive</b>. Les dates seront de nouveau proposées à d'autres voyageurs.</x-ui.alerte>
        @if($erreur)<x-ui.alerte type="erreur">{{ $erreur }}</x-ui.alerte>@endif
        <div class="grid gap-3 sm:grid-cols-2">
            <button wire:click="confirmer" wire:loading.attr="disabled" class="btn btn-danger btn-bloc">Confirmer l'annulation @if($apercu['rembourse'] > 0)(remboursé : {{ $fr($apercu['rembourse']) }})@endif</button>
            <a href="{{ route('mes-reservations') }}" class="btn btn-second btn-bloc">Garder ma réservation</a>
        </div>
    @endif
</div>
