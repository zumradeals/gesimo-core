@php use App\Sejours\Enums\StatutReservation as S; $fcfa = fn (int $m) => \App\Noyau\Services\Format::fcfa($m); @endphp
<div style="display:grid;gap:16px" wire:poll.30s>
    <div class="adm-top"><div><h1>Mes réservations</h1><p class="petit">Retrouvez vos séjours à venir et passés</p></div><div class="adm-act"><a href="{{ route('recherche') }}" class="btn-pl">Chercher un séjour</a></div></div>
    <div class="onglets-a deux" style="max-width:520px">
        <button type="button" wire:click="$set('onglet', 'avenir')" class="{{ $onglet === 'avenir' ? 'on' : '' }}" style="border:0;background:{{ $onglet === 'avenir' ? '#fff' : 'transparent' }};padding:10px;border-radius:9px;font-weight:700;cursor:pointer;min-height:44px">À venir ({{ $nbAVenir }})</button>
        <button type="button" wire:click="$set('onglet', 'passees')" class="{{ $onglet === 'passees' ? 'on' : '' }}" style="border:0;background:{{ $onglet === 'passees' ? '#fff' : 'transparent' }};padding:10px;border-radius:9px;font-weight:700;cursor:pointer;min-height:44px">Passées ({{ $nbPassees }})</button>
    </div>
    @if(session('message')) <x-ui.alerte type="succes">{{ session('message') }}</x-ui.alerte> @endif
    @if($erreur) <x-ui.alerte type="erreur">{{ $erreur }}</x-ui.alerte> @endif

    <div class="liste-r" style="margin-top:0">
    @forelse($reservations as $r)
        @php $u = $r->unite; $res = $u->residence; @endphp
        <article class="carte-p res-card">
            <div><x-ui.photo :photo="$u->photos->first() ?? $res->photos->first()" classe="h-[110px]" :alt="'Photo de '.$u->nom" /></div>
            <div class="ri">
                <div class="ri-t"><b><a href="{{ route('reservation.detail', $r) }}">{{ $u->nom }}</a></b><x-ui.badge-statut :statut="$r->statut" /></div>
                <span class="petit">{{ $res->nom }} · {{ $res->commune }} · #{{ $r->id }}</span>
                <span>{{ $r->date_arrivee->translatedFormat('j M Y') }} → {{ $r->date_depart->translatedFormat('j M Y') }} · {{ $r->date_arrivee->diffInDays($r->date_depart) }} nuit(s)</span>
                <b>{{ $fcfa($r->montant_total) }}</b>
                @if($r->statut === S::EnAttente)
                    <span class="petit rouge"><x-ui.icone nom="horloge" classe="ic pt" /> Dates gardées encore {{ $r->expire_le->diffForHumans(['parts' => 2, 'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE]) }}</span>
                    @if(\App\Paiement\Models\Paiement::where('reservation_id', $r->id)->where('statut', 'en_attente')->exists())
                        <a href="{{ route('paiement.retour', $r) }}" class="lien-fl">Un paiement est en cours de vérification</a>
                    @endif
                @elseif(in_array($r->statut, [S::Confirmee, S::ArriveeEnregistree], true))
                    <span class="petit">Gérant : {{ $res->gerant->nom }} · {{ \App\Noyau\Services\Telephone::afficher($res->gerant->telephone) }}@if($res->latitude !== null) · <a class="lien-fl" style="display:inline" href="https://www.google.com/maps?q={{ $res->latitude }},{{ $res->longitude }}" target="_blank" rel="noopener">Voir sur la carte</a>@endif</span>
                @elseif($r->statut === S::Expiree)
                    <span class="petit">Le délai de garde est passé : les dates ont été libérées.</span>
                @endif
            </div>
            <div class="ri-a">
                @if($r->statut === S::EnAttente)
                    <a href="{{ route('payer', $r) }}" data-reseau class="btn-pl">Payer {{ $fcfa($r->acompte) }}</a>
                    <button data-reseau wire:click="annuler({{ $r->id }})" wire:confirm="Annuler cette réservation ?" class="btn-sec rouge-b2">Annuler cette réservation</button>
                @else
                    <a href="{{ route('reservation.detail', $r) }}" class="btn-sec">Détail</a>
                    @if(in_array($r->statut, [S::Confirmee, S::ArriveeEnregistree], true))<a href="{{ route('reservation.recu', $r) }}" data-reseau target="_blank" class="btn-sec">Reçu</a>@endif
                    @if($r->statut === S::Confirmee)<a href="{{ route('reservation.annuler', $r) }}" data-reseau class="btn-sec rouge-b2">Annuler</a>@endif
                @endif
            </div>
        </article>
    @empty
        <div class="carte-p" style="text-align:center;padding:40px 18px;justify-items:center">
            <x-ui.icone nom="liste" classe="ic gros" />
            <p style="font-size:18px;font-weight:700;margin:0">{{ $onglet === 'passees' ? 'Aucun séjour passé.' : 'Aucune réservation à venir pour le moment.' }}</p>
            <a href="{{ route('recherche') }}" class="btn-pl">Chercher un séjour</a>
        </div>
    @endforelse
    </div>
</div>
