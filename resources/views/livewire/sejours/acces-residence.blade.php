<div class="mr-page ev-page">
    <p class="fil"><a href="{{ route('gerant.residence', $r) }}">‹ {{ $r->nom }}</a></p>
    <div class="mr-entete"><div><h1>Accès à la résidence</h1><p class="petit">Aidez vos voyageurs à vous trouver, sans appel.</p></div></div>
    <div class="ev-cols">
        <form wire:submit="enregistrer" class="mr-carte" x-data>
            <h2>Où se trouve la résidence ?</h2>
            @if($message)<x-ui.alerte type="succes">{{ $message }}</x-ui.alerte>@endif
            @if($erreur)<x-ui.alerte type="erreur">{{ $erreur }}</x-ui.alerte>@endif
            <div class="mr-carte rv-ivoire"><b>Le plus simple : placez-vous devant la résidence</b>
                <button type="button" class="btn-pl pleine"
                    x-on:click="navigator.geolocation ? navigator.geolocation.getCurrentPosition(p => $wire.positionRecue(p.coords.latitude, p.coords.longitude), () => $wire.positionRefusee(), { enableHighAccuracy: true, timeout: 15000 }) : $wire.positionRefusee()">📍 Utiliser ma position actuelle</button>
                <p class="petit" style="margin:0">Votre téléphone indique sa position. Elle n’est donnée au voyageur qu’après la confirmation de sa réservation.</p></div>
            <div class="champ"><label class="etiquette" for="lien">Ou collez un lien Google Maps</label><small class="petit">Ouvrez la résidence dans Google Maps, touchez « Partager », copiez le lien complet.</small>
                <input id="lien" wire:model="lien" wire:change="lireLien" class="saisie" placeholder="https://www.google.com/maps/…" inputmode="url" autocomplete="off">
                @error('lien')<p class="erreur-champ">{{ $message }}</p>@enderror</div>
            @if($aPosition)
                <div class="gq-carte" aria-hidden="true"><span><b>⌂</b></span></div>
                <p class="petit" style="margin:0">Position : {{ $latitude }}° · {{ $longitude }}° · <button type="button" wire:click="retirerPosition" class="mr-lien">Retirer</button></p>
            @endif
            <div class="champ"><label class="etiquette" for="adresse">Adresse écrite</label><small class="petit">Visible du voyageur seulement après confirmation.</small><input id="adresse" wire:model="adresse" maxlength="255" class="saisie">@error('adresse')<p class="erreur-champ">{{ $message }}</p>@enderror</div>
            <div class="champ"><label class="etiquette" for="reperes">Repères pour arriver</label><small class="petit">2 ou 3 phrases : ce que le voyageur verra en route. 300 caractères au maximum.</small><textarea id="reperes" wire:model="reperes" rows="3" class="saisie py-2" placeholder="Ex. À 200 m de la pharmacie Djibi, portail bleu, en face du maquis. Sonnez à l’interphone."></textarea>@error('reperes')<p class="erreur-champ">{{ $message }}</p>@enderror</div>
            <button class="btn-pl pleine" wire:loading.attr="disabled" wire:target="enregistrer">Enregistrer</button>
        </form>
        <aside class="mr-col">
            <section class="mr-carte"><h2>Ce que verra le voyageur</h2><p class="petit" style="margin:0">Après confirmation de sa réservation :</p>
                @if($aPosition)<div class="gq-carte" aria-hidden="true"><span><b>⌂</b></span></div>@endif
                @if($apercu->adresse || $apercu->quartier || $apercu->commune)<div class="ligne"><span>Adresse</span><b style="text-align:right">{{ collect([$apercu->adresse, $apercu->quartier, $apercu->commune])->filter()->implode(', ') }}</b></div>@endif
                @if($apercu->reperes)<p class="petit" style="margin:0">« {{ $apercu->reperes }} »</p>@endif
                @if($aPosition)<span class="btn-pl pleine" style="pointer-events:none">Me conduire à la résidence</span>@else<p class="petit" style="margin:0">Sans position, le voyageur voit seulement l’adresse et les repères.</p>@endif
            </section>
            <x-ui.alerte type="info">Avant la confirmation, le public voit seulement la commune.</x-ui.alerte>
        </aside>
    </div>
</div>
