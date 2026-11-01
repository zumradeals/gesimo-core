@php
    use App\Sejours\Enums\StatutReservation as S;
    $fcfa = fn (int $m) => \App\Noyau\Services\Format::fcfa($m);
    $aujourdhui = now()->startOfDay();
    // « Arrivée dans 3 jours » : repère simple, calculé sur les dates de la réservation.
    $arrivee = function ($r) use ($aujourdhui) {
        $jours = (int) $aujourdhui->diffInDays($r->date_arrivee->startOfDay(), false);

        return match (true) {
            $jours < 0 => null,
            $jours === 0 => 'Arrivée aujourd\'hui',
            $jours === 1 => 'Arrivée demain',
            default => "Arrivée dans {$jours} jours",
        };
    };
    $reste = fn ($r) => $r->expire_le->diffForHumans(['parts' => 2, 'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE]);
@endphp
<div class="mr-page" wire:poll.30s>
    <div class="mr-entete"><div><h1>Mes réservations</h1><p class="petit">Retrouvez vos séjours à venir et passés</p></div><a href="{{ route('recherche') }}" class="btn-pl">Chercher un séjour</a></div>
    <div class="mr-onglets" role="tablist">
        <button type="button" role="tab" aria-selected="{{ $onglet === 'avenir' ? 'true' : 'false' }}" wire:click="$set('onglet', 'avenir')" class="{{ $onglet === 'avenir' ? 'on' : '' }}">À venir ({{ $nbAVenir }})</button>
        <button type="button" role="tab" aria-selected="{{ $onglet === 'passees' ? 'true' : 'false' }}" wire:click="$set('onglet', 'passees')" class="{{ $onglet === 'passees' ? 'on' : '' }}">Passées ({{ $nbPassees }})</button>
    </div>
    @if(session('message')) <x-ui.alerte type="succes">{{ session('message') }}</x-ui.alerte> @endif
    @if($erreur) <x-ui.alerte type="erreur">{{ $erreur }}</x-ui.alerte> @endif

    @if($aFaire)
        @php $f = $aFaire['reservation']; @endphp
        <section class="mr-etape" aria-label="À faire en premier">
            @if($aFaire['type'] === 'paiement')
                <span class="mr-surtitre">À faire en premier</span>
                <h2>Payez pour garder vos dates</h2>
                <p>{{ $f->unite->nom }}, {{ $f->unite->residence->commune }} : vos dates sont gardées encore <b>{{ $reste($f) }}</b>. Passé ce délai, elles sont libérées.</p>
                <a href="{{ route('payer', $f) }}" data-reseau class="btn-pl grand">Payer {{ $fcfa($f->acompte) }}</a>
            @elseif($aFaire['type'] === 'piece')
                <span class="mr-surtitre">À faire en premier</span>
                <h2>Ajoutez votre pièce d'identité</h2>
                <p>Le gérant de {{ $f->unite->residence->nom }} en a besoin pour vous accueillir. Vous ne le ferez qu'une fois pour toutes vos réservations.</p>
                <a href="{{ route('reservation.detail', $f) }}#piece-identite" class="btn-pl grand">Ajouter ma pièce d'identité</a>
            @else
                <span class="mr-surtitre">Votre avis compte</span>
                <h2>Comment s'est passé votre séjour à {{ $f->unite->residence->commune }} ?</h2>
                <p>Votre avis aide les prochains voyageurs à choisir en confiance.</p>
                <a href="{{ route('reservation.avis', $f) }}" data-reseau class="btn-pl grand">Donner mon avis</a>
            @endif
        </section>
    @endif

    <div class="mr-liste">
    @forelse($reservations as $r)
        @php $u = $r->unite; $res = $u->residence; $a = $avis->get($r->id); $enCoursSejour = $r->statut === S::ArriveeEnregistree; @endphp
        <article class="mr-carte">
            <a href="{{ route('reservation.detail', $r) }}" class="mr-photo" tabindex="-1" aria-hidden="true"><x-ui.photo :photo="$u->photos->first() ?? $res->photos->first()" classe="h-[110px]" :alt="'Photo de '.$u->nom" /></a>
            <div class="mr-corps">
                <div class="mr-haut">
                    <h3><a href="{{ route('reservation.detail', $r) }}">{{ $u->nom }}</a></h3>
                    @if($r->statut === S::EnAttente)<span class="mr-badge att">En attente de paiement</span>@else<x-ui.badge-statut :statut="$r->statut" />@endif
                </div>
                <span class="petit">{{ $res->nom }} · {{ $res->commune }} · n° {{ $r->id }}</span>
                <span>{{ $r->date_arrivee->translatedFormat('j M') }} → {{ $r->date_depart->translatedFormat('j M Y') }} · {{ $r->date_arrivee->diffInDays($r->date_depart) }} nuit(s)</span>
                <b>{{ $fcfa($r->montant_total) }}</b>
                @if($r->statut === S::EnAttente)
                    <span class="mr-compte">⏱ Dates gardées encore {{ $reste($r) }}</span>
                    @if(\App\Paiement\Models\Paiement::where('reservation_id', $r->id)->where('statut', 'en_attente')->exists())
                        <a href="{{ route('paiement.retour', $r) }}" class="lien-fl">Un paiement est en cours de vérification</a>
                    @endif
                @elseif($r->statut === S::Confirmee)
                    @if($arrivee($r))<span class="mr-compte">📅 {{ $arrivee($r) }}</span>@endif
                    @if($pieceFournie)<span class="mr-rappel ok">✓ Pièce d'identité fournie</span>
                    @else<span class="mr-rappel">🪪 Pièce d'identité à ajouter avant votre arrivée</span>@endif
                @elseif($enCoursSejour)
                    <span class="mr-compte">🏠 Séjour en cours</span>
                @elseif($r->statut === S::Expiree)
                    <span class="petit">Le délai de garde est passé : les dates ont été libérées.</span>
                @elseif($r->statut === S::Annulee && ($rembourse[$r->id] ?? 0) > 0)
                    <span class="petit">Remboursé : {{ $fcfa((int) $rembourse[$r->id]) }}</span>
                @elseif($r->statut === S::Terminee && $a)
                    <span class="petit">Votre avis : <x-ui.etoiles :note="$a->note" /></span>
                @endif
            </div>
            <div class="mr-actions">
                @if($r->statut === S::EnAttente)
                    <a href="{{ route('payer', $r) }}" data-reseau class="btn-pl">Payer {{ $fcfa($r->acompte) }}</a>
                    <button type="button" data-reseau wire:click="annuler({{ $r->id }})" wire:confirm="Annuler cette réservation ?" class="mr-lien">Annuler cette réservation</button>
                @elseif($r->statut === S::Confirmee && ! $pieceFournie)
                    <a href="{{ route('reservation.detail', $r) }}#piece-identite" class="btn-pl">Ajouter ma pièce d'identité</a>
                    <a href="{{ route('reservation.detail', $r) }}" class="btn-sec">Voir le détail</a>
                @elseif($r->statut === S::Terminee)
                    @unless($a)<a href="{{ route('reservation.avis', $r) }}" data-reseau class="btn-pl">Donner mon avis</a>@endunless
                    <a href="{{ route('logement', $u) }}" class="btn-sec">Réserver à nouveau</a>
                    <a href="{{ route('reservation.detail', $r) }}" class="mr-lien">Voir le détail</a>
                @elseif(in_array($r->statut, [S::Annulee, S::Expiree], true))
                    <a href="{{ route('reservation.detail', $r) }}" class="mr-lien">Voir le détail</a>
                @else
                    <a href="{{ route('reservation.detail', $r) }}" class="btn-sec">Voir le détail</a>
                @endif
            </div>
        </article>
    @empty
        <div class="mr-vide">
            <x-ui.icone nom="liste" classe="ic gros" />
            <h2>{{ $onglet === 'passees' ? 'Aucun séjour passé' : 'Aucune réservation à venir' }}</h2>
            <p class="petit">Trouvez un logement meublé avec le prix total affiché avant de payer.</p>
            <a href="{{ route('recherche') }}" class="btn-pl">Chercher un séjour</a>
        </div>
    @endforelse
    </div>
</div>
