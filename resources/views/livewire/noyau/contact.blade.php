<div class="grid gap-4 md:grid-cols-2 md:items-start">
    <form wire:submit="envoyer" class="carte grid gap-3">
        <h1 class="text-2xl font-extrabold">Une question ? Écrivez-nous</h1>
        @if($envoye)<x-ui.alerte type="succes">Merci ! Votre message est bien parti. Nous vous répondons sous 24 h.</x-ui.alerte>@endif
        @if($erreur)<x-ui.alerte type="erreur">{{ $erreur }}</x-ui.alerte>@endif
        <div class="champ"><label class="etiquette" for="nom">Votre nom</label><input id="nom" wire:model="nom" class="saisie" autocomplete="name">@error('nom')<p class="erreur-champ">{{ $message }}</p>@enderror</div>
        <div class="champ"><label class="etiquette" for="contact">Votre e-mail ou téléphone</label><input id="contact" wire:model="contact" class="saisie">@error('contact')<p class="erreur-champ">{{ $message }}</p>@enderror</div>
        <div class="champ"><label class="etiquette" for="message">Votre message</label><textarea id="message" wire:model="message" rows="5" class="saisie py-2"></textarea>@error('message')<p class="erreur-champ">{{ $message }}</p>@enderror</div>
        <button class="btn btn-bloc" wire:loading.attr="disabled">Envoyer</button>
    </form>
    <div class="carte grid gap-2">
        <h2 class="text-lg font-extrabold">Autres moyens</h2>
        @if($emailEquipe)<div class="flex justify-between gap-2 border-b border-dashed border-trait py-1"><span>E-mail</span><span>{{ $emailEquipe }}</span></div>@endif
        <div class="flex justify-between gap-2 border-b border-dashed border-trait py-1"><span>Réponse</span><span>sous 24 h (jours ouvrés)</span></div>
        <p class="aide">Un problème avec une réservation ? Ouvrez-la depuis « Mes réservations » puis « Signaler un problème ».</p>
        <a href="{{ route('faq') }}" class="font-semibold text-marque-600 underline">Voir les questions fréquentes</a>
    </div>
</div>
