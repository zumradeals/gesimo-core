@php $fr = fn (int $m) => \App\Noyau\Services\Format::fcfa($m); $u = $r->unite; $res = $u->residence; @endphp
<div class="mx-auto grid max-w-5xl gap-4">
    <p class="text-[13px] font-bold text-marque-600"><span class="text-gris-doux">1. Récapitulatif › 2. Paiement ›</span> 3. Confirmation</p>
    <div class="carte grid justify-items-center gap-2 bg-green-50 py-8 text-center">
        <div class="flex h-16 w-16 items-center justify-center rounded-full bg-green-100 text-3xl text-green-800">✔</div>
        <h1 class="text-2xl font-extrabold">Votre réservation est confirmée</h1>
        <p>Réservation n° <b>{{ $r->id }}</b> · un e-mail de confirmation est envoyé à {{ auth()->user()->email }}</p>
        @if($tardif)<x-ui.alerte type="info">Votre paiement est arrivé après le délai de garde, mais les dates étaient encore libres : tout est en ordre.</x-ui.alerte>@endif
    </div>
    <div class="grid gap-4 md:grid-cols-2">
        <div class="carte">
            <h2 class="mb-2 text-xl font-extrabold">Votre séjour</h2>
            <div class="flex justify-between border-b border-dashed border-trait py-2"><span>Logement</span><b>{{ $u->nom }}</b></div>
            <div class="flex justify-between border-b border-dashed border-trait py-2"><span>Résidence</span><span>{{ $res->nom }} · {{ $res->commune }}@if($res->quartier), {{ $res->quartier }}@endif</span></div>
            <div class="flex justify-between border-b border-dashed border-trait py-2"><span>Arrivée</span><span>{{ $r->date_arrivee->translatedFormat('D j M Y') }}</span></div>
            <div class="flex justify-between border-b border-dashed border-trait py-2"><span>Départ</span><span>{{ $r->date_depart->translatedFormat('D j M Y') }}</span></div>
            <div class="flex justify-between border-b border-dashed border-trait py-2"><span>Déjà payé en ligne</span><b class="text-green-700">{{ $fr($paiement->montant) }}</b></div>
            @if($r->solde > 0)<div class="flex justify-between py-2"><span>Reste à payer au gérant à l'arrivée</span><b>{{ $fr($r->solde) }}</b></div>@endif
        </div>
        <div class="carte">
            <h2 class="mb-2 text-xl font-extrabold">Contact du gérant</h2>
            <p class="font-bold">{{ $res->gerant->nom }}</p>
            <p>{{ \App\Noyau\Services\Telephone::afficher($res->gerant->telephone) }}</p>
            @if($res->latitude !== null)<p class="mt-2"><a class="font-bold text-marque-600 underline" target="_blank" rel="noopener" href="https://www.google.com/maps?q={{ $res->latitude }},{{ $res->longitude }}">Voir sur la carte</a></p>@endif
            @if($res->reglement)<div class="my-3 h-px bg-trait"></div><p class="aide !text-[14px]">Règlement : {{ $res->reglement }}</p>@endif
        </div>
    </div>
    <div class="grid gap-3 sm:grid-cols-2 md:max-w-2xl">
        <a href="{{ route('reservation.recu', $r) }}" target="_blank" class="btn btn-bloc">Télécharger mon reçu</a>
        <a href="{{ route('mes-reservations') }}" class="btn btn-second btn-bloc">Mes réservations</a>
    </div>
</div>
