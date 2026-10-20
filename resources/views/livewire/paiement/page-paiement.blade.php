@php $fr = fn (int $m) => \App\Noyau\Services\Format::fcfa($m); $u = $r->unite; @endphp
<div class="mx-auto grid max-w-5xl gap-4">
    <a href="{{ route('mes-reservations') }}" class="inline-flex items-center gap-1 font-semibold text-marque-600"><x-ui.icone nom="fleche-gauche" classe="h-5 w-5" /> Mes réservations</a>
    <p class="text-[13px] font-bold text-marque-600"><span class="text-gris-doux">1. Récapitulatif ›</span> 2. Paiement <span class="text-gris-doux">› 3. Confirmation</span></p>

    @if($modeTest)<x-ui.alerte type="attention"><b>MODE TEST :</b> aucun vrai argent n'est débité.</x-ui.alerte>@endif

    @if(! $enAttente)
        <x-ui.alerte type="erreur">Le délai de garde est terminé : ces dates ne sont plus réservées pour vous. <a class="font-bold underline" href="{{ route('accueil') }}">Refaire une réservation</a>.</x-ui.alerte>
    @else
    <div class="grid gap-4 md:grid-cols-2">
        <div class="carte">
            <h1 class="mb-2 text-xl font-extrabold">Votre séjour</h1>
            <div class="flex justify-between border-b border-dashed border-trait py-2"><span>Logement</span><b>{{ $u->nom }}</b></div>
            <div class="flex justify-between border-b border-dashed border-trait py-2"><span>Résidence</span><span>{{ $u->residence->nom }}, {{ $u->residence->commune }}</span></div>
            <div class="flex justify-between border-b border-dashed border-trait py-2"><span>Dates</span><span>{{ $r->date_arrivee->translatedFormat('j M') }} → {{ $r->date_depart->translatedFormat('j M Y') }} ({{ $r->nombre_nuits ?? $r->date_arrivee->diffInDays($r->date_depart) }} nuits)</span></div>
            <div class="flex justify-between border-b border-dashed border-trait py-2"><span>Total du séjour</span><span>{{ $fr($r->montant_total) }}</span></div>
            @if($resteAuGerant > 0)<div class="flex justify-between py-2"><span>À régler au gérant à l'arrivée</span><span>{{ $fr($resteAuGerant) }}</span></div>@endif
        </div>
        <div class="carte">
            <h2 class="mb-1 text-xl font-extrabold">À payer maintenant</h2>
            <p class="text-3xl font-extrabold">{{ $fr($r->acompte) }} <span class="text-sm font-normal text-gris-doux">{{ $resteAuGerant > 0 ? 'acompte' : 'total du séjour' }}</span></p>
            <div class="my-3 h-px bg-trait"></div>
            <p class="mb-2 font-semibold">Moyens de paiement <span class="aide font-normal">(vous choisissez sur la page sécurisée de GeniusPay)</span></p>
            <div class="flex flex-wrap gap-2 text-[15px] font-bold"><span class="rounded-xl border-[1.5px] border-trait bg-white px-3 py-2">Wave</span><span class="rounded-xl border-[1.5px] border-trait bg-white px-3 py-2">Orange Money</span><span class="rounded-xl border-[1.5px] border-trait bg-white px-3 py-2">MTN Money</span><span class="rounded-xl border-[1.5px] border-trait bg-white px-3 py-2">Carte bancaire</span></div>
        </div>
    </div>

    <x-ui.alerte type="attention"><x-ui.icone nom="horloge" classe="mr-1 inline h-5 w-5" />
        Il vous reste <b x-data="{ fin: {{ $r->expire_le->getTimestamp() }}, reste: '' }" x-init="const maj = () => { const s = Math.max(0, this.fin - Math.floor(Date.now() / 1000)); this.reste = Math.floor(s / 3600) + ' h ' + String(Math.floor(s % 3600 / 60)).padStart(2, '0') + ' min'; }; maj(); setInterval(maj, 20000)" x-text="reste">{{ $r->expire_le->diffForHumans(['syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE, 'parts' => 2]) }}</b> pour payer. Passé ce délai, les dates sont libérées.</x-ui.alerte>

    @if($erreur)<x-ui.alerte type="erreur">{{ $erreur }}</x-ui.alerte>@endif
    @if(! $disponible)<x-ui.alerte type="erreur">Le paiement en ligne est momentanément indisponible. Vos dates restent gardées : réessayez plus tard.</x-ui.alerte>@endif

    <button wire:click="payer" wire:loading.attr="disabled" class="btn btn-bloc md:w-auto" @disabled(! $disponible)>Payer {{ $fr($r->acompte) }}</button>
    <p class="aide">Vous quittez GESIMO pour la page de paiement sécurisée de GeniusPay, puis vous revenez ici. Nous ne voyons jamais votre numéro de carte ni votre code secret.</p>
    @endif
</div>
