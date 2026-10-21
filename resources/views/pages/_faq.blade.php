@php
    $delai = \App\Parametres\Services\Reglages::entier('annulation.delai_gratuit_heures');
    $min = \App\Parametres\Services\Reglages::entier('reservation.duree_blocage_minutes');
    $blocage = $min % 60 === 0 ? ($min / 60).' heure'.($min >= 120 ? 's' : '') : $min.' minutes';
@endphp
<div class="faq">
@foreach([
['Comment payer ?','Par Wave, Orange Money, MTN Money ou carte bancaire, sur la page sécurisée de notre partenaire de paiement. Vous payez l\'acompte ou le total, selon la résidence.'],
['Mes dates sont-elles gardées ?','Oui, pendant '.$blocage.'. Sans paiement dans ce délai, elles sont libérées automatiquement.'],
['Puis-je annuler ?','Oui. Remboursement intégral jusqu\'à '.$delai.' heures avant l\'arrivée. Ensuite, une nuit est retenue et le reste est remboursé.'],
['Mon paiement est passé mais ma réservation n\'est pas confirmée ?','Ne payez pas une deuxième fois. La confirmation arrive dès que le paiement est vérifié. Si les dates ne sont plus libres, vous êtes remboursé automatiquement.'],
['Quand le gérant est-il payé ?','Après l\'arrivée du client. Avelis retient sa commission puis reverse le reste au gérant.'],
['Comment devenir gérant ?','Créez un compte gérant, ajoutez votre résidence et vos pièces. Nous vérifions sous quelques jours.'],
] as [$q, $r])
<details @if($loop->first) open @endif><summary>{{ $q }}</summary><p>{{ $r }}</p></details>
@endforeach
</div>
