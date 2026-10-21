<div style="display:grid;gap:14px;max-width:640px">
    <p class="fil"><a href="{{ $retour }}">← Retour</a></p>
    @if($fait)
        <div class="carte-p succes-b"><div class="text-4xl">✔</div><h1 class="text-xl font-extrabold">Votre message est envoyé à l'équipe Avelis.</h1><p class="aide">Nous vous répondons sous 48 h. Un e-mail a été envoyé aux deux parties.</p><a href="{{ $retour }}" class="btn-sec">Retour</a></div>
    @else
    <form wire:submit="envoyer" class="carte-p">
        <h1 class="text-xl font-extrabold">Signaler un problème</h1>
        <p class="aide">Réservation n° {{ $r->id }} · {{ $r->unite->nom }} · {{ $r->date_arrivee->translatedFormat('j M') }} → {{ $r->date_depart->translatedFormat('j M Y') }}</p>
        @if($erreur)<x-ui.alerte type="erreur">{{ $erreur }}</x-ui.alerte>@endif
        <div class="champ"><label class="etiquette" for="motif">Que s'est-il passé ?</label><textarea id="motif" wire:model="motif" rows="5" class="saisie py-2" placeholder="Expliquez simplement…" maxlength="2000"></textarea></div>
        <x-ui.alerte type="attention">Si la réservation est confirmée ou en cours, elle passe « En litige » jusqu'à la réponse de l'équipe. <b>Aucun remboursement n'est automatique</b> : l'équipe Avelis étudie la situation.</x-ui.alerte>
        <button class="btn-pl grand pleine" wire:loading.attr="disabled">Envoyer à l'équipe Avelis</button>
    </form>
    @endif
</div>
