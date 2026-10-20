@php $fr = fn (int $m) => \App\Noyau\Services\Format::fcfa($m); @endphp
<div class="grid gap-4">
    <h1 class="text-2xl font-extrabold">Arrivées et départs</h1>
    @if($message)<x-ui.alerte type="succes">{{ $message }}</x-ui.alerte>@endif
    @if($erreur)<x-ui.alerte type="erreur">{{ $erreur }}</x-ui.alerte>@endif

    @if($residence === null)
        <x-ui.alerte type="attention">Aucune résidence à afficher : vous n'avez pas encore le droit « arrivées et départs ».</x-ui.alerte>
    @else
    <div class="grid gap-3 md:max-w-3xl md:grid-cols-2">
        <div class="champ"><label class="etiquette" for="residence">Résidence</label>
            <select id="residence" wire:model.live="residenceId" class="saisie">@foreach($residences as $r)<option value="{{ $r->id }}">{{ $r->nom }} ({{ $r->commune }})</option>@endforeach</select></div>
        <div class="champ"><label class="etiquette" for="jour">Jour</label><input id="jour" type="date" wire:model.live="jour" class="saisie"></div>
    </div>

    <h2 class="text-lg font-extrabold">Arrivées ({{ $attendues->count() }})</h2>
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse($attendues as $a)
            <article class="carte grid gap-1.5">
                <div><x-ui.badge-statut :statut="$a->statut" /></div>
                <h3 class="text-lg font-extrabold">{{ $a->client?->nom }} · {{ $a->unite->nom }}</h3>
                <p class="aide">{{ $a->nombre_personnes }} pers. · {{ $a->date_arrivee->translatedFormat('j M') }} → {{ $a->date_depart->translatedFormat('j M') }}@if($a->client) · {{ \App\Noyau\Services\Telephone::afficher($a->client->telephone) }}@endif</p>
                @if($a->statut === \App\Sejours\Enums\StatutReservation::Confirmee)
                    @if($a->solde > 0)<div class="flex justify-between border-t border-dashed border-trait pt-2"><span>À encaisser vous-même à l'arrivée</span><b>{{ $fr($a->solde) }}</b></div>@endif
                    <button wire:click="arrivee({{ $a->id }})" wire:loading.attr="disabled" class="btn btn-bloc mt-1">Enregistrer l'arrivée</button>
                    <button wire:click="absent({{ $a->id }})" wire:confirm="« Client absent » est définitif : le client ne sera pas remboursé. Confirmer ?" class="btn btn-danger btn-bloc">Client absent</button>
                    @can('update', $residence)<button wire:click="annulerParLeGerant({{ $a->id }})" wire:confirm="Annuler cette réservation ? Le client sera remboursé en totalité." class="mt-1 text-left text-sm font-semibold text-red-700">Annuler (le client est remboursé en totalité)</button>@endcan
                @else
                    <x-ui.alerte type="erreur">Acompte non payé : cette réservation n'est pas confirmée. Ne faites pas entrer le client avant la confirmation.</x-ui.alerte>
                @endif
            </article>
        @empty
            <p class="aide md:col-span-2">Aucune arrivée prévue ce jour.</p>
        @endforelse
    </div>

    <h2 class="text-lg font-extrabold">Dans la résidence ({{ $presents->count() }})</h2>
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse($presents as $p)
            <article class="carte grid gap-1.5">
                <div><x-ui.badge-statut :statut="$p->statut" /></div>
                <h3 class="text-lg font-extrabold">{{ $p->client?->nom }} · {{ $p->unite->nom }}</h3>
                <p class="aide">Départ prévu le {{ $p->date_depart->translatedFormat('D j M') }}{{ $p->date_depart->isSameDay($jourDate) ? ' (aujourd\'hui)' : '' }}</p>
                <button wire:click="depart({{ $p->id }})" wire:loading.attr="disabled" class="btn btn-bloc mt-1">Enregistrer le départ</button>
            </article>
        @empty
            <p class="aide md:col-span-2">Personne pour le moment.</p>
        @endforelse
    </div>
    <x-ui.alerte type="info"><b>Ce que ça déclenche :</b> l'arrivée enregistrée rend votre part « à reverser » par GESIMO (voir « Argent »). Le départ termine le séjour. « Client absent » : le client n'est pas remboursé et votre part est aussi « à reverser ».</x-ui.alerte>
    @endif
</div>
