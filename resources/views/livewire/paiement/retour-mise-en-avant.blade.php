<div class="mx-auto grid max-w-xl gap-4" @if($etat === 'verification') wire:poll.4s="actualiser" @endif>
    <div class="carte-p">
        @if($etat === 'ok')
            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-green-100 text-3xl text-green-800">✔</div>
            <h1 class="text-xl font-extrabold">Paiement reçu</h1>
            <p>Votre mise en avant « {{ $m->offre }} » pour <b>{{ $m->residence->nom }}</b> est enregistrée : du {{ $m->date_debut->translatedFormat('j M') }} au {{ $m->date_fin->translatedFormat('j M Y') }}.</p>
            <a href="{{ route('gerant.mises-en-avant') }}" class="btn-pl pleine">Mes mises en avant</a>
        @elseif($etat === 'echec')
            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-red-100 text-3xl text-red-700">✖</div>
            <h1 class="text-xl font-extrabold">Paiement non abouti</h1>
            <p>Le paiement a échoué ou a été annulé. <b>Vous n'avez pas été débité.</b></p>
            <a href="{{ route('gerant.mise-en-avant.acheter') }}" class="btn-pl pleine">Réessayer</a>
        @elseif($etat === 'aucun')
            <h1 class="text-xl font-extrabold">Aucun paiement trouvé</h1>
            <a href="{{ route('gerant.mise-en-avant.acheter') }}" class="btn-pl pleine">Payer maintenant</a>
        @else
            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-amber-100 text-3xl text-amber-700">⏳</div>
            <h1 class="text-xl font-extrabold">Vérification en cours…</h1>
            <p>Nous vérifions votre paiement auprès de GeniusPay. <b>Ne payez pas une deuxième fois.</b> Cette page se met à jour toute seule.</p>
            @if($fournisseurInjoignable)<x-ui.alerte type="attention">GeniusPay ne répond pas pour le moment. Nous réessayons automatiquement.</x-ui.alerte>@endif
            <button wire:click="actualiser" class="btn-sec pleine">Vérifier maintenant</button>
        @endif
    </div>
</div>
