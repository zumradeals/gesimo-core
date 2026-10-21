<div class="auth"><div class="auth-g">
    <aside class="auth-vis"><h2>Content de vous revoir</h2><ul class="garanties clair"><li><x-ui.icone nom="ok" classe="ic" /> Retrouvez vos réservations et vos reçus</li><li><x-ui.icone nom="ok" classe="ic" /> Réservez en quelques minutes</li><li><x-ui.icone nom="ok" classe="ic" /> Paiement sécurisé par Mobile Money ou carte</li></ul></aside>
    <div class="auth-f">
        <div class="onglets-a"><span class="on">Se connecter</span><a href="{{ route('inscription') }}">Créer un compte</a></div>
    <form wire:submit="seConnecter" class="grid content-start gap-3">
        <h1>Se connecter</h1>

        @if($erreur) <x-ui.alerte type="erreur">{{ $erreur }}</x-ui.alerte> @endif
        @if($info) <x-ui.alerte type="succes">{{ $info }}</x-ui.alerte> @endif

        <div class="champ">
            <label class="etiquette" for="telephone">Téléphone</label>
            <input id="telephone" type="tel" inputmode="tel" autocomplete="tel" wire:model="telephone" class="saisie" placeholder="07 01 02 03 04">
            @error('telephone') <p class="erreur-champ">{{ $message }}</p> @enderror
        </div>

        @if($smsActif)
            <div class="rounded-xl bg-marque-50 p-3">
                <p class="font-semibold">Recevoir un code par SMS</p>
                @if(! $codeEnvoye)
                    <button type="button" wire:click="envoyerCode" class="btn btn-second mt-2 btn-bloc">Envoyer le code</button>
                @else
                    <div class="champ mt-2">
                        <label class="etiquette" for="code">Code reçu par SMS</label>
                        <input id="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" wire:model="code" class="saisie tracking-[0.4em]">
                        @error('code') <p class="erreur-champ">{{ $message }}</p> @enderror
                    </div>
                    <button type="button" wire:click="verifierCode" class="btn mt-2 btn-bloc">Valider le code</button>
                @endif
            </div>
            <p class="aide text-center">— ou avec votre mot de passe —</p>
        @endif

        <div class="champ">
            <label class="etiquette" for="motDePasse">Mot de passe</label>
            <input id="motDePasse" type="password" autocomplete="current-password" wire:model="motDePasse" class="saisie">
            @error('motDePasse') <p class="erreur-champ">{{ $message }}</p> @enderror
        </div>

        <button class="btn btn-bloc" wire:loading.attr="disabled">Me connecter</button>
        <p class="aide text-center">— ou —</p>
        <button type="button" wire:click="envoyerLien" class="btn btn-second btn-bloc">Recevoir un lien par e-mail</button>
        <p class="aide">Le lien arrive dans votre boîte e-mail. Il ne marche qu'une fois, pendant 15 minutes.</p>
    </form>
    </div>
</div></div>
