<div>
    <x-site.bandeau titre="Contact" sous="Une question, un problème, une suggestion ? Nous vous répondons sous 24 h." surtitre="Aide et contact" />
    <section class="sect"><div class="cont deux-col top ct-grille">
        <form wire:submit="envoyer" class="carte-form">
            <h2 style="margin:0;font-size:22px">Une question ? Écrivez-nous</h2>
            @if($envoye)<x-ui.alerte type="succes">Merci ! Votre message est bien parti. Nous vous répondons sous 24 h.</x-ui.alerte>@endif
            @if($erreur)<x-ui.alerte type="erreur">{{ $erreur }}</x-ui.alerte>@endif
            <div class="champ"><label class="etiquette" for="nom">Votre nom</label><input id="nom" wire:model="nom" class="saisie" autocomplete="name">@error('nom')<p class="erreur-champ">{{ $message }}</p>@enderror</div>
            <div class="champ"><label class="etiquette" for="contact">Votre e-mail ou téléphone</label><input id="contact" wire:model="contact" class="saisie">@error('contact')<p class="erreur-champ">{{ $message }}</p>@enderror</div>
            <div class="champ"><label class="etiquette" for="message">Votre message</label><textarea id="message" wire:model="message" rows="5" class="saisie py-2"></textarea>@error('message')<p class="erreur-champ">{{ $message }}</p>@enderror</div>
            <button class="btn-pl grand pleine" wire:loading.attr="disabled">Envoyer</button>
        </form>
        <div style="display:grid;gap:18px;align-content:start">
            @include('pages._coordonnees')
            <div class="carte-p pi-rapide">
                <h2 style="margin:0;font-size:18px">Une réponse rapide ?</h2>
                <p style="margin:0">Paiement, annulation, remboursement : les réponses sont dans les questions fréquentes.</p>
                <a href="{{ route('faq') }}" class="btn-sec">Voir les questions fréquentes</a>
            </div>
        </div>
    </div></section>
</div>
