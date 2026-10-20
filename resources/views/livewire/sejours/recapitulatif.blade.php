@php
    $fr = fn (int $m) => \App\Noyau\Services\Format::fcfa($m);
    $r = $u->residence;
    $duree = $dureeMinutes % 60 === 0 ? ($dureeMinutes / 60).' heure'.($dureeMinutes > 60 ? 's' : '') : $dureeMinutes.' minutes';
@endphp
<div class="mx-auto grid max-w-5xl gap-4">
    <a href="{{ route('logement', ['unite' => $u->id, 'arrivee' => $arrivee, 'depart' => $depart, 'personnes' => $personnes]) }}" class="inline-flex items-center gap-1 font-semibold text-marque-600"><x-ui.icone nom="fleche-gauche" classe="h-5 w-5" /> Modifier</a>
    <p class="text-[13px] font-bold text-marque-600">1. Récapitulatif <span class="text-gris-doux">› 2. Paiement › 3. Confirmation</span></p>

    @if(! $valide)
        <x-ui.alerte type="erreur">Les dates ne sont pas valides. Revenez à la fiche du logement pour les choisir.</x-ui.alerte>
    @elseif($total === null)
        <x-ui.alerte type="attention">Ce logement n'a pas encore de prix.</x-ui.alerte>
    @else
        <div class="grid gap-4 md:grid-cols-2">
            <div class="carte">
                <h1 class="mb-2 text-xl font-extrabold">Votre séjour</h1>
                <div class="flex justify-between border-b border-dashed border-trait py-2"><span>Logement</span><b>{{ $u->nom }}</b></div>
                <div class="flex justify-between border-b border-dashed border-trait py-2"><span>Résidence</span><span>{{ $r->nom }}, {{ $r->commune }}</span></div>
                <div class="flex justify-between border-b border-dashed border-trait py-2"><span>Arrivée</span><span>{{ \Carbon\CarbonImmutable::parse($arrivee)->translatedFormat('D j M Y') }}</span></div>
                <div class="flex justify-between border-b border-dashed border-trait py-2"><span>Départ</span><span>{{ \Carbon\CarbonImmutable::parse($depart)->translatedFormat('D j M Y') }}</span></div>
                <div class="flex justify-between py-2"><span>Nuits / personnes</span><span>{{ $nuits }} / {{ $personnes }}</span></div>
            </div>
            <div class="carte">
                <h2 class="mb-2 text-xl font-extrabold">Prix</h2>
                <p class="text-3xl font-extrabold">{{ $fr($total) }} <span class="text-sm font-normal text-gris-doux">total du séjour</span></p>
                <div class="mt-2 flex justify-between border-b border-dashed border-trait py-2"><span>{{ $solde > 0 ? 'À payer en ligne (acompte)' : 'À payer en ligne' }}</span><b>{{ $fr($acompte) }}</b></div>
                @if($solde > 0)<div class="flex justify-between py-2"><span>À payer au gérant à l'arrivée</span><span>{{ $fr($solde) }}</span></div>@endif
                <p class="aide mt-1">Aucun autre frais.</p>
            </div>
        </div>

        <x-ui.alerte type="attention"><x-ui.icone nom="horloge" classe="mr-1 inline h-5 w-5" /> En touchant le bouton, ces dates sont gardées pour vous pendant <b>{{ $duree }}</b>. Sans confirmation dans ce délai, elles sont libérées.</x-ui.alerte>

        @if($erreur) <x-ui.alerte type="erreur">{{ $erreur }}</x-ui.alerte> @endif
        @if(! $libre && ! $erreur) <x-ui.alerte type="erreur">Ces dates ne sont plus libres. Choisissez d'autres dates.</x-ui.alerte> @endif

        <button wire:click="garder" wire:loading.attr="disabled" class="btn btn-bloc sm:w-auto" @disabled(! $libre)>Garder ces dates {{ $duree }}</button>
        <p class="aide">Ensuite, vous payez {{ \App\Noyau\Services\Format::fcfa($acompte) }} en ligne (Wave, Orange Money, MTN Money ou carte).</p>
    @endif
</div>
