<div class="mx-auto grid max-w-xl gap-4">
    <div class="grid grid-cols-2 overflow-hidden rounded-2xl border border-trait bg-white text-center font-bold">
        <a href="{{ route('connexion') }}" wire:navigate class="p-3 text-gris-doux">Se connecter</a>
        <span class="bg-marque-600 p-3 text-white">Créer un compte</span>
    </div>

    <form wire:submit="inscrire" class="carte grid gap-3">
        <h1 class="text-xl font-extrabold">Créer un compte</h1>

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
        <button class="btn btn-bloc" wire:loading.attr="disabled">Créer mon compte</button>
    </form>
</div>
