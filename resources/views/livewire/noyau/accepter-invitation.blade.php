@if($invitation === null)
    <x-auth.carte icone="⏱" ton="r">
        <h1>Invitation expirée</h1>
        <x-ui.alerte type="erreur">Cette invitation n'est plus valable. Demandez-en une nouvelle au gérant.</x-ui.alerte>
        <div class="en-actions"><a href="{{ route('accueil') }}" class="btn btn-second btn-bloc">Aller à l'accueil</a></div>
    </x-auth.carte>
@else
    <x-auth.carte icone="🔑" class="gauche">
        <span class="en-code">Invitation</span>
        <h1>Rejoindre {{ $invitation->residence->nom }}</h1>
        <p>{{ $invitation->residence->gerant->nom }} vous invite comme <b>{{ collect([$invitation->peut_gerer_calendrier ? 'calendrier' : null, $invitation->peut_gerer_checkin_checkout ? 'arrivées et départs' : null])->filter()->implode(' et ') }}</b>.</p>
        @if($erreur)<x-ui.alerte type="erreur">{{ $erreur }}</x-ui.alerte>@endif

        @if($compteExiste && ! $connecteCommeInvite)
            <x-ui.alerte type="info">Un compte existe déjà avec {{ $invitation->email }}. Connectez-vous, puis rouvrez ce lien.</x-ui.alerte>
            <a href="{{ route('connexion') }}" class="btn btn-bloc">Se connecter</a>
        @elseif($compteExiste)
            <button wire:click="accepter" class="btn btn-bloc">Accepter l'invitation</button>
        @else
            <form wire:submit="accepter" class="grid gap-3" novalidate>
                <p class="en-aide">Compte : {{ $invitation->nom }} · {{ $invitation->email }}</p>
                <div class="champ" x-data="{ voir: false }">
                    <label class="etiquette" for="mdp">Choisissez un mot de passe</label>
                    <span class="en-aide">8 caractères minimum.</span>
                    <div class="en-mdp">
                        <input id="mdp" :type="voir ? 'text' : 'password'" type="password" autocomplete="new-password" wire:model="motDePasse" class="saisie">
                        <button type="button" class="en-voir" @click="voir = ! voir" :aria-pressed="voir" x-text="voir ? 'Masquer' : 'Afficher'">Afficher</button>
                    </div>
                    @error('motDePasse')<p class="erreur-champ">{{ $message }}</p>@enderror
                </div>
                <button class="btn btn-bloc" wire:loading.attr="disabled">Créer mon accès</button>
            </form>
        @endif
    </x-auth.carte>
@endif
