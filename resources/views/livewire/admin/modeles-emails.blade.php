<div class="mr-page ev-page">
<div class="mr-entete"><div><h1>Modèles d'e-mails</h1><p class="petit">Les textes envoyés par la plateforme.</p></div></div>
<div class="ev-grille">
    <div class="mr-carte">
        <p class="aide">Les mots entre accolades sont remplacés automatiquement à l'envoi : @foreach($mots as $mot => $sens)<code>{{ '{'.$mot.'}' }}</code>{{ $loop->last ? '' : ', ' }}@endforeach.</p>
        @foreach($modeles as $code => $m)
            <button wire:click="modifier('{{ $code }}')" class="flex min-h-[52px] items-center justify-between gap-2 border-b border-dashed border-trait py-2 text-left {{ $choisi === $code ? 'font-bold text-marque-600' : '' }}"><span>{{ $m['titre'] }} <span class="aide">→ {{ $m['destinataire'] }}</span></span><span class="font-bold text-marque-600">Modifier</span></button>
        @endforeach
        <p class="aide mt-2">E-mail aujourd'hui ; SMS et WhatsApp pourront s'ajouter plus tard sans changer ces modèles.</p>
    </div>
    @if($choisi)
    <form wire:submit="enregistrer" class="mr-carte">
        <h2>{{ $modeles[$choisi]['titre'] }}</h2>
        @if($message)<x-ui.alerte type="succes">{{ $message }}</x-ui.alerte>@endif
        @if($erreur)<x-ui.alerte type="erreur">{{ $erreur }}</x-ui.alerte>@endif
        <div class="champ"><label class="etiquette" for="sujet">Sujet</label><input id="sujet" wire:model="sujet" class="saisie" maxlength="190"></div>
        <div class="champ"><label class="etiquette" for="corps">Message</label><textarea id="corps" wire:model="corps" rows="9" class="saisie py-2" maxlength="5000"></textarea></div>
        <button class="btn-pl pleine">Enregistrer</button>
        <button type="button" wire:click="essai" class="btn-sec pleine">M'envoyer un essai</button>
    </form>
    @endif
</div>
</div>
