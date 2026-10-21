@php $fr = fn (int $m) => \App\Noyau\Services\Format::fcfa($m); @endphp
<div class="mx-auto grid max-w-3xl gap-4">
    <a href="{{ route('gerant.mises-en-avant') }}" class="text-[15px] font-semibold text-marque-600">← Mes mises en avant</a>
    <div class="rounded-2xl bg-gradient-to-br from-marque-600 to-marque-500 p-5 text-white"><div class="adm-top"><div><h1>Faites passer votre résidence en tête ⭐</h1></div></div><p class="opacity-95">Votre annonce apparaît en premier dans les recherches, avec la mention « Sponsorisé ».</p></div>
    @if($erreur)<x-ui.alerte type="erreur">{{ $erreur }}</x-ui.alerte>@endif
    @if(! $disponible)<x-ui.alerte type="attention">Le paiement en ligne n'est pas encore disponible. Réessayez plus tard.</x-ui.alerte>@endif
    @if($modeTest && $disponible)<x-ui.alerte type="attention">MODE TEST : aucun vrai argent n'est débité.</x-ui.alerte>@endif

    @if($residences->isEmpty())
        <div class="carte-p"><p class="font-semibold">Vous n'avez pas encore de résidence.</p><a href="{{ route('gerant.residence.nouvelle') }}" class="font-bold text-marque-600 underline">Ajouter une résidence</a></div>
    @elseif($offres === [])
        <div class="carte-p"><p class="font-semibold">Aucune offre n'est proposée pour le moment.</p><p class="aide">Revenez bientôt.</p></div>
    @else
    <div class="carte-p"><h2 class="font-extrabold">1. Choisissez la résidence</h2>
        <select wire:model="residenceId" class="saisie" aria-label="Résidence">@foreach($residences as $r)<option value="{{ $r->id }}">{{ $r->nom }} · {{ $r->commune }}{{ $r->estPubliee() ? '' : ' (non vérifiée)' }}</option>@endforeach</select></div>
    <div class="carte-p"><h2 class="font-extrabold">2. Choisissez la durée</h2>
        <div class="grid gap-2 sm:grid-cols-2" role="radiogroup" aria-label="Durée">
            @foreach($offres as $i => $o)
                <button type="button" wire:click="$set('offre', {{ $i }})" role="radio" aria-checked="{{ $offre === $i ? 'true' : 'false' }}" class="rounded-2xl border-2 p-3 text-left {{ $offre === $i ? 'border-marque-600 bg-marque-50' : 'border-trait bg-white' }}"><b>{{ $o['nom'] }}</b><br>{{ $o['jours'] }} jour{{ $o['jours'] > 1 ? 's' : '' }} · <b>{{ $fr($o['prix']) }}</b></button>
            @endforeach
        </div>
        <p class="aide">La mise en avant commence dès que le paiement est confirmé. Si une mise en avant est déjà en cours, la nouvelle période commence juste après.</p>
        <x-ui.alerte type="attention">Une mise en avant payée n'est pas remboursable.</x-ui.alerte></div>
    <button wire:click="payer" wire:loading.attr="disabled" @disabled(! $disponible) class="btn-pl">Payer {{ $fr($offres[$offre]['prix'] ?? 0) }}</button>
    <p class="aide">Vous quittez Avelis pour la page de paiement sécurisée de GeniusPay, puis vous revenez ici.</p>
    @endif
</div>
