<x-auth.entree titre="Content de vous revoir" texte="Retrouvez vos séjours, vos reçus et vos réservations en un instant."
    :points="['Retrouvez vos réservations et vos reçus', 'Réservez en quelques minutes', 'Paiement sécurisé par Mobile Money ou carte']">
    <form wire:submit="seConnecter" novalidate>
        <div class="en-onglets"><span aria-current="page">Se connecter</span><a href="{{ route('inscription') }}">Créer un compte</a></div>
        <h1>Se connecter</h1>

        @if(str_contains((string) session('url.intended'), '/reserver/'))<x-ui.alerte type="info"><b>Vos dates sont conservées.</b> Connectez-vous ou créez un compte (1 minute) : vous reprenez ensuite exactement là où vous en étiez.</x-ui.alerte>@endif

        @if($erreur) <x-ui.alerte type="erreur">{{ $erreur }}</x-ui.alerte> @endif
        @if($info) <x-ui.alerte type="succes">{{ $info }}</x-ui.alerte> @endif

        <div class="champ">
            <label class="etiquette" for="telephone">Téléphone</label>
            <span class="en-aide">10 chiffres, sans espace ni indicatif.</span>
            <input id="telephone" type="tel" inputmode="tel" autocomplete="tel" wire:model="telephone" class="saisie" placeholder="07 01 02 03 04">
            @error('telephone') <p class="erreur-champ">{{ $message }}</p> @enderror
        </div>

        @if($smsActif)
            <div class="en-sms">
                <p class="font-semibold">Recevoir un code par SMS</p>
                @if(! $codeEnvoye)
                    <button type="button" wire:click="envoyerCode" class="btn btn-second btn-bloc">Envoyer le code</button>
                @else
                    <div class="champ">
                        <label class="etiquette" for="code">Code reçu par SMS</label>
                        <input id="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" wire:model="code" class="saisie tracking-[0.4em]">
                        @error('code') <p class="erreur-champ">{{ $message }}</p> @enderror
                    </div>
                    <button type="button" wire:click="verifierCode" class="btn btn-bloc">Valider le code</button>
                @endif
            </div>
            <div class="en-ou">ou avec votre mot de passe</div>
        @endif

        <div class="champ" x-data="{ voir: false }">
            <label class="etiquette" for="motDePasse">Mot de passe</label>
            <div class="en-mdp">
                <input id="motDePasse" :type="voir ? 'text' : 'password'" type="password" autocomplete="current-password" wire:model="motDePasse" class="saisie">
                <button type="button" class="en-voir" @click="voir = ! voir" :aria-pressed="voir" x-text="voir ? 'Masquer' : 'Afficher'">Afficher</button>
            </div>
            @error('motDePasse') <p class="erreur-champ">{{ $message }}</p> @enderror
        </div>

        <button class="btn btn-bloc" wire:loading.attr="disabled">Me connecter</button>
        <div class="en-ou">ou</div>
        <button type="button" wire:click="envoyerLien" class="btn btn-second btn-bloc">Mot de passe oublié ? Recevoir un lien par e-mail</button>
        <p class="en-aide">Le lien arrive dans votre boîte e-mail. Il ne marche qu'une fois, pendant 15 minutes.</p>
        <p class="en-aide" style="text-align:center">Pas encore de compte ? <a href="{{ route('inscription') }}">Créer un compte</a></p>
    </form>
</x-auth.entree>
