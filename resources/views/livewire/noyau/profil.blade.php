<div class="mr-page ev-page">
    <div class="mr-entete"><div><h1>{{ $estClient ? 'Mon profil voyageur' : 'Mon profil' }}</h1></div></div>
    @if($message)<x-ui.alerte type="succes">{{ $message }}</x-ui.alerte>@endif
    @if($erreur)<x-ui.alerte type="erreur">{{ $erreur }}</x-ui.alerte>@endif
    <div class="ev-grille">
        <form wire:submit="enregistrer" class="mr-carte">
            <h2>Mes informations</h2>
            <div class="champ"><label class="etiquette" for="nom">Nom</label><input id="nom" wire:model="nom" class="saisie" autocomplete="name">@error('nom')<p class="erreur-champ">{{ $message }}</p>@enderror</div>
            <div class="champ"><label class="etiquette" for="telephone">Téléphone</label><small class="petit">10 chiffres, sans espace ni indicatif.</small><input id="telephone" type="tel" inputmode="tel" wire:model="telephone" class="saisie">@error('telephone')<p class="erreur-champ">{{ $message }}</p>@enderror</div>
            <div class="champ"><label class="etiquette" for="email">E-mail</label><input id="email" type="email" wire:model="email" class="saisie">@error('email')<p class="erreur-champ">{{ $message }}</p>@enderror</div>
            <button class="btn-pl" wire:loading.attr="disabled">Enregistrer</button>
        </form>
        <form wire:submit="changerMotDePasse" class="mr-carte" x-data="{ voir: false }">
            <h2>Mot de passe</h2>
            <div class="champ"><label class="etiquette" for="ancien">Ancien mot de passe</label>
                <div class="ev-mdp"><input id="ancien" :type="voir ? 'text' : 'password'" wire:model="ancienMotDePasse" class="saisie" autocomplete="current-password"><button type="button" @click="voir = ! voir" x-text="voir ? 'Masquer' : 'Afficher'">Afficher</button></div></div>
            <div class="champ"><label class="etiquette" for="nouveau">Nouveau mot de passe</label><small class="petit">8 caractères minimum.</small>
                <div class="ev-mdp"><input id="nouveau" :type="voir ? 'text' : 'password'" wire:model="nouveauMotDePasse" class="saisie" autocomplete="new-password"><button type="button" @click="voir = ! voir" x-text="voir ? 'Masquer' : 'Afficher'">Afficher</button></div>
                @error('nouveauMotDePasse')<p class="erreur-champ">{{ $message }}</p>@enderror</div>
            <button class="btn-sec" wire:loading.attr="disabled">Changer mon mot de passe</button>
        </form>
        @if($estClient)
            <section class="mr-carte" id="piece-identite">
                <h2>Ma pièce d'identité</h2>
                @livewire(\App\Sejours\Livewire\PieceIdentite::class, ['source' => 'profil'])
            </section>
            <section class="mr-carte rv-ivoire">
                <h2>Qui peut voir ma pièce ?</h2>
                <ul class="rv-liste">
                    <li>Le gérant de votre réservation, une fois celle-ci confirmée et payée</li>
                    <li>Pendant votre séjour et quelques jours après votre départ seulement</li>
                    <li>Chaque ouverture est enregistrée</li>
                    <li>Jamais le public, jamais les autres gérants</li>
                </ul>
                <p class="petit" style="margin:0">Vous pouvez la supprimer quand vous n'avez aucune réservation en cours.</p>
            </section>
        @endif
    </div>
</div>
