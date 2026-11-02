<div class="mr-page ev-page">
    <p class="fil"><a href="{{ $retour }}">← Retour</a></p>
    <div class="ev-centre">
    @if($fait)
        <div class="mr-carte ev-succes"><div class="ev-gros-ico">✔</div><h1>Votre message est envoyé à l'équipe Avelis.</h1><p class="petit" style="margin:0">Nous vous répondons sous 48 h. Un e-mail a été envoyé aux deux parties.</p><a href="{{ $retour }}" class="btn-sec">Retour</a></div>
    @else
    <form wire:submit="envoyer" class="mr-carte">
        <h1>Signaler un problème</h1>
        <p class="petit" style="margin:0">Réservation n° {{ $r->id }} · {{ $r->unite->nom }} · {{ $r->date_arrivee->translatedFormat('j M') }} → {{ $r->date_depart->translatedFormat('j M Y') }}</p>
        @if($erreur)<x-ui.alerte type="erreur">{{ $erreur }}</x-ui.alerte>@endif
        <div class="champ"><label class="etiquette" for="motif">Que s'est-il passé ?</label><textarea id="motif" wire:model="motif" rows="5" class="saisie py-2" placeholder="Expliquez simplement…" maxlength="2000"></textarea></div>
        <div class="ev-suite"><b>Et ensuite ?</b>
            <ol><li>L'équipe Avelis lit votre message.</li><li>Vous recevez une réponse sous 48 h.</li><li>Un e-mail est envoyé aux deux parties.</li></ol></div>
        <x-ui.alerte type="attention">Si la réservation est confirmée ou en cours, elle passe « En litige » jusqu'à la réponse de l'équipe. <b>Aucun remboursement n'est automatique</b> : l'équipe Avelis étudie la situation.</x-ui.alerte>
        <button class="btn-pl grand pleine" wire:loading.attr="disabled">Envoyer à l'équipe Avelis</button>
    </form>
    @endif
    </div>
</div>
