<div class="carte grid gap-1 md:max-w-3xl">
<h1 class="mb-2 text-2xl font-extrabold">Questions fréquentes</h1>
@foreach([
['Comment payer ?','Par Wave, Orange Money, MTN Money ou carte bancaire, sur la page sécurisée de notre partenaire de paiement. Vous payez l\'acompte ou le total, selon la résidence.'],
['Mes dates sont-elles gardées ?','Oui, pendant 2 heures. Sans paiement dans ce délai, elles sont libérées automatiquement.'],
['Puis-je annuler ?','Oui. Remboursement intégral jusqu\'à 24 heures avant l\'arrivée. Ensuite, une nuit est retenue et le reste est remboursé.'],
['Mon paiement est passé mais ma réservation n\'est pas confirmée ?','Ne payez pas une deuxième fois. La confirmation arrive dès que le paiement est vérifié. Si les dates ne sont plus libres, vous êtes remboursé automatiquement.'],
['Quand le gérant est-il payé ?','Après l\'arrivée du client. GESIMO retient sa commission puis reverse le reste au gérant.'],
['Comment devenir gérant ?','Créez un compte gérant, ajoutez votre résidence et vos pièces. Nous vérifions sous quelques jours.'],
] as [$q, $r])
<details class="border-b border-dashed border-trait py-2"><summary class="min-h-[44px] cursor-pointer py-2 font-bold">{{ $q }}</summary><p class="pb-2 text-gris-doux">{{ $r }}</p></details>
@endforeach
<p class="aide mt-2">Une autre question ? <a class="font-semibold text-marque-600 underline" href="{{ route('contact') }}">Écrivez-nous</a>.</p></div>
