@php
    use App\Sejours\Enums\HeureArriveePrevue;
    use App\Sejours\Enums\MotifSejour;
    use App\Sejours\Services\PiecesVoyageur;
    $etat = PiecesVoyageur::etat($r);
    $motif = $r->motif_sejour ? MotifSejour::tryFrom($r->motif_sejour) : null;
@endphp
@if($r->origine === 'plateforme')
<div class="rv-voyageur">
    @if($r->nom_client && $r->client && $r->nom_client !== $r->client->nom)<div class="ligne"><span>Nom sur la pièce</span><b>{{ $r->nom_client }}</b></div>@endif
    @if($motif)<div class="ligne"><span>Motif du séjour</span><b>{{ $motif === MotifSejour::Autre && $r->motif_precision ? 'Autre : '.$r->motif_precision : $motif->libelle() }}</b></div>@endif
    @if($r->heure_arrivee_prevue && $r->heure_arrivee_prevue !== 'inconnue')<div class="ligne"><span>Arrivée prévue</span><b>{{ HeureArriveePrevue::from($r->heure_arrivee_prevue)->libelle() }}</b></div>@endif
    @if($r->message_gerant)<p class="rv-message"><b>Message :</b> « {{ $r->message_gerant }} »</p>@endif
    <div class="ligne"><span>Pièce d'identité</span>
        <span class="rv-badge {{ $etat === PiecesVoyageur::ETAT_A_FOURNIR ? 'att' : 'ok' }}">{{ PiecesVoyageur::libelleEtat($etat) }}</span></div>
    @if($etat === PiecesVoyageur::ETAT_A_FOURNIR && $r->statut === \App\Sejours\Enums\StatutReservation::Confirmee)
        <p class="petit" style="margin:0">Pièce d'identité non fournie : demandez-la à l'arrivée. L'arrivée n'est pas bloquée, vous décidez à l'accueil.</p>
    @endif
    @can('voirPieceVoyageur', $r)
        @if($etat !== PiecesVoyageur::ETAT_A_FOURNIR)
            <a class="btn-sec" href="{{ route('gerant.piece-voyageur', $r) }}" target="_blank" rel="noopener">Voir la pièce (ouverture enregistrée)</a>
        @endif
    @endcan
</div>
@endif
