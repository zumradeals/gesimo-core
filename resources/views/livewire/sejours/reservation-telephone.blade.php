<div class="mr-page ev-page"><div class="ev-centre">
    <p class="fil"><a href="{{ route('gerant.calendrier') }}">‹ Calendrier</a></p>
    <h1>Réservation par téléphone</h1>
    <x-ui.alerte type="info">Cette réservation est <b>gratuite</b>, sans paiement en ligne et <b>sans commission</b>. Elle sert seulement à bloquer le calendrier.</x-ui.alerte>

    @if($unites->isEmpty())
        <x-ui.alerte type="attention">Vous n'avez pas encore de logement actif.</x-ui.alerte>
    @else
    <form wire:submit="enregistrer" class="carte-p">
        <div class="champ"><label class="etiquette" for="unite">Logement</label>
            <select id="unite" wire:model="uniteId" class="saisie">@foreach($unites as $u)<option value="{{ $u->id }}">{{ $u->nom }} — {{ $u->residence->nom }}</option>@endforeach</select>
            @error('uniteId')<p class="erreur-champ">{{ $message }}</p>@enderror</div>
        <div class="grid grid-cols-2 gap-3">
            <div class="champ"><label class="etiquette" for="arrivee">Arrivée</label><input id="arrivee" type="date" wire:model="arrivee" min="{{ now()->toDateString() }}" class="saisie">@error('arrivee')<p class="erreur-champ">{{ $message }}</p>@enderror</div>
            <div class="champ"><label class="etiquette" for="depart">Départ</label><input id="depart" type="date" wire:model="depart" min="{{ $arrivee ?: now()->toDateString() }}" class="saisie">@error('depart')<p class="erreur-champ">{{ $message }}</p>@enderror</div>
        </div>
        <div class="champ"><label class="etiquette" for="nomClient">Nom du client</label><input id="nomClient" wire:model="nomClient" class="saisie" placeholder="Ex. M. Traoré">@error('nomClient')<p class="erreur-champ">{{ $message }}</p>@enderror</div>
        <div class="champ"><label class="etiquette" for="telClient">Téléphone du client (facultatif)</label><input id="telClient" type="tel" inputmode="tel" wire:model="telephoneClient" class="saisie" placeholder="05 11 22 33 44"></div>
        @if($erreur)<x-ui.alerte type="erreur">{{ $erreur }}</x-ui.alerte>@endif
        <button class="btn-pl pleine" wire:loading.attr="disabled">Enregistrer</button>
    </form>
    @endif
</div>
</div>
