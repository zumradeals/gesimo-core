<div class="carte grid gap-1 md:max-w-3xl">
<h1 class="mb-2 text-2xl font-extrabold">Aide du gérant</h1>
@foreach([
['Dois-je renvoyer ma pièce pour chaque résidence ?','Non. Fournissez votre pièce d’identité dans « Mon profil gérant ». Après validation par Avelis, elle sert pour toutes vos résidences.'],
['Le certificat de résidence est-il obligatoire ?','Non. Il est facultatif et permet, après contrôle en plus de votre identité, la mention « Gérant certifié ».'],
['Faut-il un justificatif de gestion pour publier ?','Non. Il est facultatif. Après contrôle, il donne le badge de la résidence concernée uniquement. L’annonce doit toujours être validée par Avelis.'],
['Que se passe-t-il après l’envoi de ma résidence ?','Vous revenez à « Mes résidences » avec une confirmation. Votre dossier reste en attente de validation ; aucun nouvel envoi n’est nécessaire.'],
['Comment enregistrer un client appelé au téléphone ?','Calendrier ▸ « Réservation par téléphone ». Elle bloque les dates, sans paiement ni commission.'],
['Quand suis-je payé ?','Après l\'arrivée du client, Avelis vous reverse votre part. Voir l\'écran « Argent ».'],
['Un client ne vient pas ?','Dans « Arrivées », utilisez « Client absent » à partir de la date d\'arrivée.'],
['Je dois annuler une réservation ?','Utilisez « Annuler » dans « Arrivées » : le client est remboursé en totalité.'],
['Un problème avec un client ?','Ouvrez la réservation puis « Signaler un problème » : l\'équipe Avelis vous répond.'],
] as [$q, $r])
<details class="border-b border-dashed border-trait py-2"><summary class="min-h-[44px] cursor-pointer py-2 font-bold">{{ $q }}</summary><p class="pb-2 text-gris-doux">{{ $r }}</p></details>
@endforeach
<a href="{{ route('contact') }}" class="btn btn-second mt-2 md:w-auto">Écrire à l'équipe</a></div>
