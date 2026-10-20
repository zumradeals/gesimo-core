<div class="grid gap-4 md:grid-cols-2 md:items-start">
    <a href="{{ route('compte') }}" class="text-[15px] font-semibold text-marque-600 md:col-span-2">← Mon compte</a>
    @if($message)<x-ui.alerte type="succes" class="md:col-span-2">{{ $message }}</x-ui.alerte>@endif
    @if($erreur)<x-ui.alerte type="erreur" class="md:col-span-2">{{ $erreur }}</x-ui.alerte>@endif
    <form wire:submit="enregistrer" class="carte grid gap-3">
        <h1 class="text-xl font-extrabold">Mes informations</h1>
        <div class="champ"><label class="etiquette" for="nom">Nom</label><input id="nom" wire:model="nom" class="saisie" autocomplete="name">@error('nom')<p class="erreur-champ">{{ $message }}</p>@enderror</div>
        <div class="champ"><label class="etiquette" for="telephone">Téléphone</label><input id="telephone" type="tel" inputmode="tel" wire:model="telephone" class="saisie">@error('telephone')<p class="erreur-champ">{{ $message }}</p>@enderror</div>
        <div class="champ"><label class="etiquette" for="email">E-mail</label><input id="email" type="email" wire:model="email" class="saisie">@error('email')<p class="erreur-champ">{{ $message }}</p>@enderror</div>
        <button class="btn btn-bloc" wire:loading.attr="disabled">Enregistrer</button>
    </form>
    <form wire:submit="changerMotDePasse" class="carte grid gap-3">
        <h2 class="text-xl font-extrabold">Mot de passe</h2>
        <div class="champ"><label class="etiquette" for="ancien">Ancien mot de passe</label><input id="ancien" type="password" wire:model="ancienMotDePasse" class="saisie" autocomplete="current-password"></div>
        <div class="champ"><label class="etiquette" for="nouveau">Nouveau mot de passe</label><input id="nouveau" type="password" wire:model="nouveauMotDePasse" class="saisie" autocomplete="new-password" placeholder="8 caractères minimum">@error('nouveauMotDePasse')<p class="erreur-champ">{{ $message }}</p>@enderror</div>
        <button class="btn btn-second btn-bloc" wire:loading.attr="disabled">Changer mon mot de passe</button>
    </form>
</div>
