@php
    $etapes = [1 => 'Compte', 2 => 'Général', 3 => 'E-mail', 4 => 'Paiement', 5 => 'Fin'];
@endphp
<div class="grid gap-4">
    <ol class="flex flex-wrap gap-x-2 gap-y-1 text-[14px] text-gris-doux" aria-label="Étapes">
        @foreach($etapes as $n => $nom)<li class="{{ $n === $etape ? 'font-extrabold text-marque-600' : '' }}">{{ $n }}. {{ $nom }}{{ $n < 5 ? ' ›' : '' }}</li>@endforeach
    </ol>

    @if($etape === 1)
        <form wire:submit="creerCompte" class="grid gap-4">
            <div class="carte grid gap-2">
                <h1 class="text-xl font-extrabold">Bienvenue sur Avelis Séjours 👋</h1>
                <p class="aide">Cette installation ne se fait qu'une seule fois. Elle crée le compte du super-administrateur puis règle l'essentiel.</p>
                @if(! $codeGenere)<x-ui.alerte type="attention">Aucun code d'installation n'a encore été généré. Sur le serveur, lancez : <b>php artisan gesimo:installation</b></x-ui.alerte>@endif
                <div class="champ"><label class="etiquette" for="code">Code d'installation</label><input id="code" wire:model="code" class="saisie uppercase" placeholder="XXXX-XXXX-XXXX" autocomplete="off">
                    <span class="aide">Ce code a été affiché sur le serveur. Il empêche un inconnu de prendre le contrôle du site avant vous.</span>
                    @if(isset($erreurs['code']))<p class="erreur-champ" role="alert">{{ $erreurs['code'] }}</p>@endif</div>
            </div>
            <div class="carte grid gap-3">
                <h2 class="text-lg font-extrabold">Vos informations (super-administrateur)</h2>
                <div class="champ"><label class="etiquette" for="nom">Nom complet</label><input id="nom" wire:model="nom" class="saisie" autocomplete="name">@error('nom')<p class="erreur-champ">{{ $message }}</p>@enderror</div>
                <div class="champ"><label class="etiquette" for="tel">Téléphone</label><input id="tel" type="tel" inputmode="tel" wire:model="telephone" class="saisie" autocomplete="tel">@error('telephone')<p class="erreur-champ">{{ $message }}</p>@enderror</div>
                <div class="champ"><label class="etiquette" for="mail">E-mail</label><input id="mail" type="email" wire:model="email" class="saisie" autocomplete="email">@error('email')<p class="erreur-champ">{{ $message }}</p>@enderror</div>
                <div class="champ"><label class="etiquette" for="mdp">Mot de passe</label><input id="mdp" type="password" wire:model="motDePasse" class="saisie" autocomplete="new-password" placeholder="8 caractères minimum">@error('motDePasse')<p class="erreur-champ">{{ $message }}</p>@enderror</div>
                <div class="champ"><label class="etiquette" for="conf">Retapez le mot de passe</label><input id="conf" type="password" wire:model="confirmation" class="saisie" autocomplete="new-password"></div>
                <button class="btn btn-bloc md:w-auto" wire:loading.attr="disabled">Créer mon compte et continuer</button>
            </div>
        </form>

    @elseif($etape === 2)
        <form wire:submit="etapeGenerale" class="carte grid gap-3">
            <h1 class="text-xl font-extrabold">Général</h1>
            <div class="champ"><label class="etiquette" for="g1">Nom de la plateforme</label><input id="g1" wire:model="valeurs.general__nom" class="saisie">@if(isset($erreurs['general.nom']))<p class="erreur-champ" role="alert">{{ $erreurs['general.nom'] }}</p>@endif</div>
            <div class="champ"><label class="etiquette" for="g2">E-mail de contact de l'équipe</label><input id="g2" type="email" wire:model="valeurs.contact__email" class="saisie"><span class="aide">Les messages du formulaire « Contact » arrivent ici.</span>@if(isset($erreurs['contact.email']))<p class="erreur-champ" role="alert">{{ $erreurs['contact.email'] }}</p>@endif</div>
            <div class="grid gap-3 sm:grid-cols-2"><button type="button" wire:click="passer" class="btn btn-second btn-bloc">Passer pour l'instant</button><button class="btn btn-bloc">Continuer</button></div>
        </form>

    @elseif($etape === 3)
        <form wire:submit="etapeEmail" class="carte grid gap-3">
            <h1 class="text-xl font-extrabold">Envoi d'e-mails <span class="aide">(confirmations, rappels)</span></h1>
            <div class="champ"><label class="etiquette" for="e1">Serveur (hôte)</label><input id="e1" wire:model="valeurs.email__hote" class="saisie" placeholder="smtp.exemple.ci">@if(isset($erreurs['email.hote']))<p class="erreur-champ" role="alert">{{ $erreurs['email.hote'] }}</p>@endif</div>
            <div class="champ"><label class="etiquette" for="e2">Port</label><input id="e2" wire:model="valeurs.email__port" inputmode="numeric" class="saisie">@if(isset($erreurs['email.port']))<p class="erreur-champ" role="alert">{{ $erreurs['email.port'] }}</p>@endif</div>
            <div class="champ"><label class="etiquette" for="e3">Utilisateur</label><input id="e3" wire:model="valeurs.email__utilisateur" class="saisie" autocomplete="off"></div>
            <div class="champ"><label class="etiquette" for="e4">Mot de passe du serveur d'e-mail 🔒</label><input id="e4" type="password" wire:model="secrets.email__mot_de_passe" class="saisie" autocomplete="off"><span class="aide">Chiffré en base, jamais réaffiché.</span></div>
            <div><button type="button" wire:click="testerEmail" class="btn btn-second btn-bloc sm:w-auto">Tester l'envoi</button></div>
            @include('livewire.parametres.resultat', ['r' => $resultat])
            <div class="grid gap-3 sm:grid-cols-2"><button type="button" wire:click="passer" class="btn btn-second btn-bloc">Passer pour l'instant</button><button class="btn btn-bloc">Continuer</button></div>
        </form>

    @elseif($etape === 4)
        <form wire:submit="etapePaiement" class="carte grid gap-3">
            <h1 class="text-xl font-extrabold">Paiement GeniusPay</h1>
            <x-ui.alerte type="attention">Commencez en <b>mode test</b> : aucun vrai argent n'est débité. Le mode réel se règle plus tard dans « Réglages ».</x-ui.alerte>
            <input type="hidden" wire:model="valeurs.geniuspay__mode">
            <div class="champ"><label class="etiquette" for="p1">Clé publique (test)</label><input id="p1" wire:model="valeurs.geniuspay__sandbox__cle_publique" class="saisie" autocomplete="off"></div>
            <div class="champ"><label class="etiquette" for="p2">Clé secrète (test) 🔒</label><input id="p2" type="password" wire:model="secrets.geniuspay__sandbox__cle_secrete" class="saisie" autocomplete="off"></div>
            <div class="champ"><label class="etiquette" for="p3">Secret du webhook (test) 🔒</label><input id="p3" type="password" wire:model="secrets.geniuspay__sandbox__webhook_secret" class="saisie" autocomplete="off"><span class="aide">Adresse du webhook à donner à GeniusPay : <b class="break-all">{{ url('/webhooks/geniuspay') }}</b></span></div>
            <div class="champ"><label class="etiquette" for="p4">Taux de commission Avelis (%)</label><input id="p4" wire:model="valeurs.commission__pourcentage" inputmode="decimal" class="saisie"><span class="aide">Obligatoire pour ouvrir le paiement en ligne.</span>@if(isset($erreurs['commission.pourcentage']))<p class="erreur-champ" role="alert">{{ $erreurs['commission.pourcentage'] }}</p>@endif</div>
            <div class="champ"><label class="etiquette" for="p5">Frais GeniusPay estimés (%)</label><input id="p5" wire:model="valeurs.paiement__frais_pourcentage" inputmode="decimal" class="saisie">@if(isset($erreurs['paiement.frais_pourcentage']))<p class="erreur-champ" role="alert">{{ $erreurs['paiement.frais_pourcentage'] }}</p>@endif</div>
            @if($plancher !== null)<div class="rounded-xl bg-marque-50 p-3 text-[15px]">Acompte minimum calculé : <b>{{ $plancher }} %</b> (commission + frais)</div>@endif
            <div class="grid gap-3 sm:grid-cols-2"><button type="button" wire:click="passer" class="btn btn-second btn-bloc">Passer pour l'instant</button><button class="btn btn-bloc">Continuer</button></div>
        </form>

    @else
        <div class="carte grid gap-3 border-green-300 bg-green-50">
            <h1 class="text-xl font-extrabold">✔ Presque terminé</h1>
            <ul class="grid gap-1 text-[15px]">@foreach($faits as $nom => $f)<li>{{ $f['ok'] ? '✔' : '○' }} {{ $nom }} : {{ $f['texte'] }}</li>@endforeach</ul>
            <button wire:click="terminer" wire:confirm="Terminer l'installation ? L'assistant ne pourra plus être relancé." class="btn btn-bloc md:w-auto">Terminer l'installation</button>
            <p class="aide">Après cela, l'assistant disparaît définitivement. Tout reste modifiable dans « Réglages ».</p>
        </div>
    @endif
</div>
