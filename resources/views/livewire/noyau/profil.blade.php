<div style="display:grid;gap:16px">
    <div class="adm-top"><div><h1>Mon profil</h1></div></div>
    @if($message)<x-ui.alerte type="succes">{{ $message }}</x-ui.alerte>@endif
    @if($erreur)<x-ui.alerte type="erreur">{{ $erreur }}</x-ui.alerte>@endif
    <div class="cols2" style="align-items:start">
        <form wire:submit="enregistrer" class="carte-p">
            <h2>Mes informations</h2>
            <div class="champ"><label class="etiquette" for="nom">Nom</label><input id="nom" wire:model="nom" class="saisie" autocomplete="name">@error('nom')<p class="erreur-champ">{{ $message }}</p>@enderror</div>
            <div class="champ"><label class="etiquette" for="telephone">Téléphone</label><input id="telephone" type="tel" inputmode="tel" wire:model="telephone" class="saisie">@error('telephone')<p class="erreur-champ">{{ $message }}</p>@enderror</div>
            <div class="champ"><label class="etiquette" for="email">E-mail</label><input id="email" type="email" wire:model="email" class="saisie">@error('email')<p class="erreur-champ">{{ $message }}</p>@enderror</div>
            <button class="btn-pl" wire:loading.attr="disabled">Enregistrer</button>
        </form>
        <form wire:submit="changerMotDePasse" class="carte-p">
            <h2>Mot de passe</h2>
            <div class="champ"><label class="etiquette" for="ancien">Ancien mot de passe</label><input id="ancien" type="password" wire:model="ancienMotDePasse" class="saisie" autocomplete="current-password"></div>
            <div class="champ"><label class="etiquette" for="nouveau">Nouveau mot de passe</label><input id="nouveau" type="password" wire:model="nouveauMotDePasse" class="saisie" autocomplete="new-password" placeholder="8 caractères minimum">@error('nouveauMotDePasse')<p class="erreur-champ">{{ $message }}</p>@enderror</div>
            <button class="btn-sec" wire:loading.attr="disabled">Changer mon mot de passe</button>
        </form>
    </div>
</div>
