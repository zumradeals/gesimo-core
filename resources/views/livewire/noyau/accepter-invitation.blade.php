<div class="mx-auto grid max-w-md gap-4">
    @if($invitation === null)
        <div class="carte grid gap-3 text-center">
            <h1 class="text-xl font-extrabold">Invitation expirée</h1>
            <x-ui.alerte type="erreur">Cette invitation n'est plus valable. Demandez-en une nouvelle au gérant.</x-ui.alerte>
        </div>
    @else
        <div class="carte grid gap-3">
            <h1 class="text-xl font-extrabold">Rejoindre {{ $invitation->residence->nom }}</h1>
            <p class="text-gris-doux">{{ $invitation->residence->gerant->nom }} vous invite comme {{ collect([$invitation->peut_gerer_calendrier ? 'calendrier' : null, $invitation->peut_gerer_checkin_checkout ? 'arrivées et départs' : null])->filter()->implode(' et ') }}.</p>
            @if($erreur)<x-ui.alerte type="erreur">{{ $erreur }}</x-ui.alerte>@endif

            @if($compteExiste && ! $connecteCommeInvite)
                <x-ui.alerte type="info">Un compte existe déjà avec {{ $invitation->email }}. Connectez-vous, puis rouvrez ce lien.</x-ui.alerte>
                <a href="{{ route('connexion') }}" class="btn btn-bloc" onclick="">Se connecter</a>
            @elseif($compteExiste)
                <button wire:click="accepter" class="btn btn-bloc">Accepter l'invitation</button>
            @else
                <form wire:submit="accepter" class="grid gap-3">
                    <p class="aide">Compte : {{ $invitation->nom }} · {{ $invitation->email }}</p>
                    <div class="champ"><label class="etiquette" for="mdp">Choisissez un mot de passe</label><input id="mdp" type="password" autocomplete="new-password" wire:model="motDePasse" class="saisie" placeholder="8 caractères minimum">@error('motDePasse')<p class="erreur-champ">{{ $message }}</p>@enderror</div>
                    <button class="btn btn-bloc" wire:loading.attr="disabled">Créer mon accès</button>
                </form>
            @endif
        </div>
    @endif
</div>
