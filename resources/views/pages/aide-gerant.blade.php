<div class="carte grid gap-1 md:max-w-3xl">
<h1 class="mb-2 text-2xl font-extrabold">Aide du gérant</h1>
@foreach([
['Comment enregistrer un client appelé au téléphone ?','Calendrier ▸ « Réservation par téléphone ». Elle bloque les dates, sans paiement ni commission.'],
['Quand suis-je payé ?','Après l\'arrivée du client, Avelis vous reverse votre part. Voir l\'écran « Argent ».'],
['Un client ne vient pas ?','Dans « Arrivées », utilisez « Client absent » à partir de la date d\'arrivée.'],
['Je dois annuler une réservation ?','Utilisez « Annuler » dans « Arrivées » : le client est remboursé en totalité.'],
['Un problème avec un client ?','Ouvrez la réservation puis « Signaler un problème » : l\'équipe Avelis vous répond.'],
] as [$q, $r])
<details class="border-b border-dashed border-trait py-2"><summary class="min-h-[44px] cursor-pointer py-2 font-bold">{{ $q }}</summary><p class="pb-2 text-gris-doux">{{ $r }}</p></details>
@endforeach
<a href="{{ route('contact') }}" class="btn btn-second mt-2 md:w-auto">Écrire à l'équipe</a></div>
