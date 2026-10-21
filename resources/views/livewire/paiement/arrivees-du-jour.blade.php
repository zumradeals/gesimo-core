@php $fr = fn (int $m) => \App\Noyau\Services\Format::fcfa($m); @endphp
<div class="grid gap-4">
    <div class="adm-top"><div><h1>Aujourd'hui</h1>@if($residence)<p class="petit">Résidence {{ $residence->nom }} · {{ $jourDate->translatedFormat('l j F') }}</p>@endif</div></div>
    @if($message)<x-ui.alerte type="succes">{{ $message }}</x-ui.alerte>@endif
    @if($erreur)<x-ui.alerte type="erreur">{{ $erreur }}</x-ui.alerte>@endif

    @if($residence === null)
        <x-ui.alerte type="attention">Aucune résidence à afficher : vous n'avez pas encore le droit « arrivées et départs ».</x-ui.alerte>
    @else
    <div class="filtres-h" style="max-width:640px;grid-template-columns:1fr 1fr">
        <div class="champ"><label class="etiquette" for="residence">Résidence</label>
            <select id="residence" wire:model.live="residenceId" class="saisie">@foreach($residences as $r)<option value="{{ $r->id }}">{{ $r->nom }} ({{ $r->commune }})</option>@endforeach</select></div>
        <div class="champ"><label class="etiquette" for="jour">Jour</label><input id="jour" type="date" wire:model.live="jour" class="saisie"></div>
    </div>

    <div class="kpi">
        <div class="k"><span class="k-i b"><x-ui.icone nom="porte" classe="ic" /></span><b>{{ $attendues->count() }}</b><span>Arrivées du jour</span></div>
        <div class="k"><span class="k-i g"><x-ui.icone nom="batiment" classe="ic" /></span><b>{{ $presents->count() }}</b><span>Dans la résidence</span></div>
        <div class="k"><span class="k-i o"><x-ui.icone nom="calendrier" classe="ic" /></span><b>{{ $nbAVenir }}</b><span>À venir (7 jours)</span></div>
        @if($sommeDue !== null)<a href="{{ route('gerant.argent') }}" class="k"><span class="k-i v"><x-ui.icone nom="argent" classe="ic" /></span><b>{{ number_format($sommeDue, 0, ',', "\u{202F}") }}</b><span>FCFA dus par Avelis</span></a>@endif
    </div>
    @if($litiges > 0)<x-ui.alerte type="attention">{{ $litiges }} problème(s) signalé(s) en cours de traitement par l'équipe Avelis. <a class="font-bold underline" href="{{ route('gerant.reservations') }}">Voir mes réservations</a></x-ui.alerte>@endif

    <h2 class="h2-s">Arrivées ({{ $attendues->count() }})</h2>
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse($attendues as $a)
            <article class="carte-p">
                <div><x-ui.badge-statut :statut="$a->statut" /></div>
                <h3 class="text-lg font-extrabold">{{ $a->client?->nom }} · {{ $a->unite->nom }}</h3>
                <p class="aide">{{ $a->nombre_personnes }} pers. · {{ $a->date_arrivee->translatedFormat('j M') }} → {{ $a->date_depart->translatedFormat('j M') }}@if($a->client) · {{ \App\Noyau\Services\Telephone::afficher($a->client->telephone) }}@endif</p>
                @if($a->statut === \App\Sejours\Enums\StatutReservation::Confirmee)
                    @if($a->solde > 0)<div class="flex justify-between border-t border-dashed border-trait pt-2"><span>À encaisser vous-même à l'arrivée</span><b>{{ $fr($a->solde) }}</b></div>@endif
                    <div class="grid gap-2 border-t border-dashed border-trait pt-2 sm:grid-cols-2">
                        <div class="champ"><label class="etiquette" for="pt{{ $a->id }}">Pièce d'identité</label>
                            <select id="pt{{ $a->id }}" wire:model="pieces.{{ $a->id }}.type" class="saisie"><option value="cni">Carte nationale</option><option value="passeport">Passeport</option><option value="permis">Permis de conduire</option><option value="autre">Autre</option></select></div>
                        <div class="champ"><label class="etiquette" for="pn{{ $a->id }}">Numéro</label><input id="pn{{ $a->id }}" wire:model="pieces.{{ $a->id }}.numero" class="saisie" autocomplete="off"></div>
                    </div>
                    <button wire:click="arrivee({{ $a->id }})" wire:loading.attr="disabled" class="btn btn-bloc mt-1">Enregistrer l'arrivée</button>
                    <button wire:click="absent({{ $a->id }})" wire:confirm="« Client absent » est définitif : le client ne sera pas remboursé. Confirmer ?" class="btn btn-danger btn-bloc">Client absent</button>
                    @can('update', $residence)<button wire:click="annulerParLeGerant({{ $a->id }})" wire:confirm="Annuler cette réservation ? Le client sera remboursé en totalité." class="mt-1 text-left text-sm font-semibold text-red-700">Annuler (le client est remboursé en totalité)</button>@endcan
                @else
                    <x-ui.alerte type="erreur">Acompte non payé : cette réservation n'est pas confirmée. Ne faites pas entrer le client avant la confirmation.</x-ui.alerte>
                @endif
            </article>
        @empty
            <p class="aide md:col-span-2">Rien à faire aujourd'hui. Profitez-en !</p>
        @endforelse
    </div>

    <h2 class="h2-s">Dans la résidence ({{ $presents->count() }})</h2>
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse($presents as $p)
            <article class="carte-p">
                <div><x-ui.badge-statut :statut="$p->statut" /></div>
                <h3 class="text-lg font-extrabold">{{ $p->client?->nom }} · {{ $p->unite->nom }}</h3>
                <p class="aide">Départ prévu le {{ $p->date_depart->translatedFormat('D j M') }}{{ $p->date_depart->isSameDay($jourDate) ? ' (aujourd\'hui)' : '' }}</p>
                <button wire:click="depart({{ $p->id }})" wire:loading.attr="disabled" class="btn btn-bloc mt-1">Enregistrer le départ</button>
            </article>
        @empty
            <p class="aide md:col-span-2">Personne pour le moment.</p>
        @endforelse
    </div>
    <x-ui.alerte type="info"><b>Ce que ça déclenche :</b> l'arrivée enregistrée rend votre part « à reverser » par Avelis (voir « Argent »). Le départ termine le séjour. « Client absent » : le client n'est pas remboursé et votre part est aussi « à reverser ».</x-ui.alerte>
    @endif
</div>
