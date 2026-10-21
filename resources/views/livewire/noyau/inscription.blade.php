<div class="auth"><div class="auth-g">
    <aside class="auth-vis"><h2>Rejoignez la plateforme</h2><ul class="garanties clair"><li><x-ui.icone nom="ok" classe="ic" /> Voyageur : réservez un séjour en toute confiance</li><li><x-ui.icone nom="ok" classe="ic" /> Gérant : recevez plus de réservations</li><li><x-ui.icone nom="ok" classe="ic" /> Gratuit, en moins de deux minutes</li></ul></aside>
    <div class="auth-f">
        <div class="onglets-a"><a href="{{ route('connexion') }}">Se connecter</a><span class="on">Créer un compte</span></div>
    <form wire:submit="inscrire" class="grid gap-3">
        <h1>Créer un compte</h1>

        <div class="champ">
            <label class="etiquette" for="nom">Votre nom</label>
            <input id="nom" autocomplete="name" wire:model="nom" class="saisie" placeholder="Ex. Awa Koné">
            @error('nom') <p class="erreur-champ">{{ $message }}</p> @enderror
        </div>
        <div class="champ">
            <label class="etiquette" for="telephone">Téléphone</label>
            <input id="telephone" type="tel" inputmode="tel" autocomplete="tel" wire:model="telephone" class="saisie" placeholder="Ex. 07 01 02 03 04">
            @error('telephone') <p class="erreur-champ">{{ $message }}</p> @enderror
        </div>
        <div class="champ">
            <label class="etiquette" for="email">E-mail</label>
            <input id="email" type="email" autocomplete="email" wire:model="email" class="saisie" placeholder="Ex. awa@exemple.com">
            <span class="aide">Nécessaire pour vous envoyer vos confirmations et votre lien de connexion.</span>
            @error('email') <p class="erreur-champ">{{ $message }}</p> @enderror
        </div>
        <div class="champ">
            <label class="etiquette" for="motDePasse">Mot de passe</label>
            <input id="motDePasse" type="password" autocomplete="new-password" wire:model="motDePasse" class="saisie" placeholder="8 caractères minimum">
            @error('motDePasse') <p class="erreur-champ">{{ $message }}</p> @enderror
        </div>
        <div class="champ">
            <span class="etiquette">Je veux…</span>
            <div class="flex flex-wrap gap-2">
                <label class="puce {{ $profil === 'client' ? 'puce-active' : '' }}"><input type="radio" class="sr-only" wire:model.live="profil" value="client">Réserver un séjour</label>
                <label class="puce {{ $profil === 'gerant' ? 'puce-active' : '' }}"><input type="radio" class="sr-only" wire:model.live="profil" value="gerant">Proposer ma résidence</label>
            </div>
        </div>
        <p class="aide">En créant un compte, vous acceptez les <a class="underline" href="{{ route('conditions') }}" target="_blank">conditions</a> et la <a class="underline" href="{{ route('confidentialite') }}" target="_blank">politique de confidentialité</a>.</p>
        <button class="btn btn-bloc" wire:loading.attr="disabled">Créer mon compte</button>
    </form>
    </div>
</div></div>
