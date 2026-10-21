@php $fr = fn (int $m) => \App\Noyau\Services\Format::fcfa($m); @endphp
<div style="max-width:640px" @if($etat === 'verification') wire:poll.4s="actualiser" @endif>
    <div class="carte-p succes-b">
        @if($etat === 'verification' || $etat === 'aucun')
            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-amber-100 text-3xl text-amber-700">⏳</div>
            @if($etat === 'aucun')
                <h1 class="text-xl font-extrabold">Aucun paiement trouvé</h1>
                <p class="text-gris-doux">Aucun paiement n'a été enregistré pour cette réservation.</p>
                <a href="{{ route('payer', $r) }}" class="btn-pl grand pleine">Payer maintenant</a>
            @else
                <h1 class="text-xl font-extrabold">Vérification en cours…</h1>
                <p>Nous vérifions votre paiement auprès de GeniusPay. <b>Ne payez pas une deuxième fois.</b> Cette page se met à jour toute seule.</p>
                @if($fournisseurInjoignable)<x-ui.alerte type="attention">GeniusPay ne répond pas pour le moment. Nous réessayons automatiquement.</x-ui.alerte>@endif
                <button wire:click="actualiser" class="btn-sec pleine">Vérifier maintenant</button>
                <p class="aide">Toujours rien après quelques minutes ? Dès que le paiement est confirmé, vous recevez un e-mail. Sinon vos dates sont libérées et vous n'êtes pas débité.</p>
            @endif
        @elseif($etat === 'echec')
            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-red-100 text-3xl text-red-700">✖</div>
            <h1 class="text-xl font-extrabold">Paiement non abouti</h1>
            <p>Le paiement a échoué ou a été annulé. <b>Vous n'avez pas été débité.</b></p>
            @if($peutReessayer)
                <p class="aide">Vos dates sont gardées jusqu'à {{ $r->expire_le->format('H \h i') }}.</p>
                <a href="{{ route('payer', $r) }}" class="btn-pl grand pleine">Réessayer le paiement</a>
            @else
                <p class="aide">Le délai de garde est terminé : refaites une réservation.</p>
                <a href="{{ route('recherche') }}" class="btn-pl grand pleine">Chercher un séjour</a>
            @endif
        @else
            {{-- paiement réussi mais réservation non confirmée : paiement arrivé trop tard, dates reprises --}}
            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-red-100 text-3xl text-red-700">↩</div>
            <h1 class="text-xl font-extrabold">Paiement remboursé</h1>
            <p>Les dates avaient été reprises par quelqu'un d'autre. Votre paiement de <b>{{ $fr($paiement->montant) }}</b> est <b>remboursé</b> automatiquement.</p>
            <p class="aide">Le remboursement peut prendre un peu de temps selon votre opérateur.</p>
            <a href="{{ route('recherche') }}" class="btn-pl grand pleine">Chercher un autre séjour</a>
        @endif
    </div>
</div>
