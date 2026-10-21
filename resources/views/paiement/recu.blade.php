@php $fr = fn (int $m) => \App\Noyau\Services\Format::fcfa($m); $u = $r->unite; @endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reçu {{ $paiement->numeroRecu() }} — Avelis Séjours</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-white p-4 text-encre sm:p-8">
<main class="mx-auto max-w-2xl rounded-2xl border border-trait p-6 print:border-0">
    <div class="flex items-start justify-between border-b border-trait pb-4">
        <div><p class="text-xl font-extrabold text-marque-600">Avelis Séjours</p><p class="text-sm text-gris-doux">Reçu de paiement</p></div>
        <p class="text-right text-sm"><b>{{ $paiement->numeroRecu() }}</b><br>{{ $paiement->updated_at->translatedFormat('j F Y, H \h i') }}</p>
    </div>
    <dl class="my-4 grid gap-2 text-[15px]">
        <div class="flex justify-between"><dt class="text-gris-doux">Client</dt><dd>{{ $r->client->nom }} · {{ \App\Noyau\Services\Telephone::afficher($r->client->telephone) }}</dd></div>
        <div class="flex justify-between"><dt class="text-gris-doux">Référence de paiement</dt><dd>{{ $paiement->reference_geniuspay }}</dd></div>
        <div class="flex justify-between"><dt class="text-gris-doux">Réservation</dt><dd>n° {{ $r->id }}</dd></div>
    </dl>
    <div class="border-y border-trait py-3 text-[15px]">
        <p class="font-bold">{{ $u->nom }} — {{ $u->residence->nom }} ({{ $u->residence->commune }})</p>
        <div class="mt-1 flex justify-between"><span>Du {{ $r->date_arrivee->translatedFormat('j M') }} au {{ $r->date_depart->translatedFormat('j M Y') }} ({{ $r->nombre_nuits ?? $r->date_arrivee->diffInDays($r->date_depart) }} nuits)</span><span>{{ $fr($r->montant_total) }}</span></div>
    </div>
    <div class="mt-3 grid gap-1 text-[15px]">
        <div class="flex justify-between text-lg font-extrabold"><span>{{ $r->solde > 0 ? 'Acompte payé en ligne' : 'Total payé en ligne' }}</span><span>{{ $fr($paiement->montant) }}</span></div>
        @if($r->solde > 0)<div class="flex justify-between"><span>Reste à régler au gérant à l'arrivée</span><span>{{ $fr($r->solde) }}</span></div>@endif
    </div>
    <p class="mt-4 text-xs text-gris-doux">Paiement encaissé par Avelis via GeniusPay. Avelis est une plateforme de mise en relation ; le solde éventuel est réglé directement au gérant.</p>
    <div class="mt-6 flex gap-3 print:hidden">
        <button onclick="window.print()" class="btn">Télécharger / imprimer (PDF)</button>
        <a href="{{ route('mes-reservations') }}" class="btn btn-second">Retour</a>
    </div>
</main>
</body>
</html>
