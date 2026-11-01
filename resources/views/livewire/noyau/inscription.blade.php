<x-auth.entree titre="Rejoignez Avelis Séjours" texte="Un compte gratuit, en moins de deux minutes."
    :points="['Voyageur : réservez un séjour en toute confiance', 'Gérant : recevez plus de réservations', 'Gratuit, en moins de deux minutes']">
    <form wire:submit="inscrire" novalidate>
        <div class="en-onglets"><a href="{{ route('connexion') }}">Se connecter</a><span aria-current="page">Créer un compte</span></div>
        <h1>Créer un compte</h1>

        @if(str_contains((string) session('url.intended'), '/reserver/'))<x-ui.alerte type="info"><b>Vos dates sont conservées.</b> Créez votre compte (1 minute) : vous reprenez ensuite exactement là où vous en étiez.</x-ui.alerte>@endif

        <div class="en-choix" role="radiogroup" aria-label="Je veux">
            <label><input type="radio" wire:model.live="profil" value="client"><div><b>Réserver un séjour</b><span>Je cherche un logement meublé en Côte d'Ivoire.</span></div></label>
            <label><input type="radio" wire:model.live="profil" value="gerant"><div><b>Proposer ma résidence</b><span>Je suis gérant : je présente mes logements et je reçois des réservations.</span></div></label>
        </div>
        @if($profil === 'gerant')<x-ui.alerte type="info"><b>Gérant :</b> votre pièce d'identité est demandée et contrôlée avant la publication de votre première annonce. Vous la déposerez depuis votre profil.</x-ui.alerte>@endif

        <div class="champ">
            <label class="etiquette" for="nom">Votre nom</label>
            <input id="nom" autocomplete="name" wire:model="nom" class="saisie" placeholder="Ex. Awa Koné">
            @error('nom') <p class="erreur-champ">{{ $message }}</p> @enderror
        </div>
        <div class="champ">
            <label class="etiquette" for="telephone">Téléphone</label>
            <span class="en-aide">10 chiffres, sans espace ni indicatif.</span>
            <input id="telephone" type="tel" inputmode="tel" autocomplete="tel" wire:model="telephone" class="saisie" placeholder="Ex. 07 01 02 03 04">
            @error('telephone') <p class="erreur-champ">{{ $message }}</p> @enderror
        </div>
        <div class="champ">
            <label class="etiquette" for="email">E-mail</label>
            <span class="en-aide">Nécessaire pour vous envoyer vos confirmations et votre lien de connexion.</span>
            <input id="email" type="email" autocomplete="email" wire:model="email" class="saisie" placeholder="Ex. awa@exemple.com">
            @error('email') <p class="erreur-champ">{{ $message }}</p> @enderror
        </div>
        <div class="champ" x-data="{ voir: false }">
            <label class="etiquette" for="motDePasse">Mot de passe</label>
            <span class="en-aide">8 caractères minimum.</span>
            <div class="en-mdp">
                <input id="motDePasse" :type="voir ? 'text' : 'password'" type="password" autocomplete="new-password" wire:model="motDePasse" class="saisie">
                <button type="button" class="en-voir" @click="voir = ! voir" :aria-pressed="voir" x-text="voir ? 'Masquer' : 'Afficher'">Afficher</button>
            </div>
            @error('motDePasse') <p class="erreur-champ">{{ $message }}</p> @enderror
        </div>
        <p class="en-aide">En créant un compte, vous acceptez les <a href="{{ route('conditions') }}" target="_blank" rel="noopener">conditions</a> et la <a href="{{ route('confidentialite') }}" target="_blank" rel="noopener">politique de confidentialité</a>.</p>
        <button class="btn btn-bloc" wire:loading.attr="disabled">Créer mon compte</button>
    </form>
</x-auth.entree>
