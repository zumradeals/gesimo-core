<div class="mr-page ev-page"><div class="mr-entete"><div><h1>Aide du gérant</h1></div></div><div class="mr-carte faq gq-aide">
@foreach([
['Dois-je renvoyer ma pièce pour chaque résidence ?','Non. Fournissez votre pièce d’identité dans « Mon profil gérant ». Après validation par Avelis, elle sert pour toutes vos résidences.'],
['Quels documents faut-il pour publier ?','Deux seulement : votre pièce d’identité (une fois) et le contrat de bail, ou tout justificatif de gestion, de chaque résidence. Après contrôle par Avelis, le badge « Gérant vérifié » apparaît. Le certificat de résidence n’est plus demandé.'],
['Comment aider mes voyageurs à trouver la résidence ?','Dans « Mes résidences », ouvrez « Accès à la résidence » : placez-vous devant la résidence, touchez « Utiliser ma position actuelle » et écrivez deux ou trois repères. Le voyageur y accède seulement après confirmation de sa réservation.'],
['Que se passe-t-il après l’envoi de ma résidence ?','Vous revenez à « Mes résidences » avec une confirmation. Votre dossier reste en attente de validation ; aucun nouvel envoi n’est nécessaire.'],
['Comment enregistrer un client appelé au téléphone ?','Calendrier ▸ « Réservation par téléphone ». Elle bloque les dates, sans paiement ni commission.'],
['Quand suis-je payé ?','Après l\'arrivée du client, Avelis vous reverse votre part. Voir l\'écran « Argent ».'],
['Un client ne vient pas ?','Dans « Arrivées », utilisez « Client absent » à partir de la date d\'arrivée.'],
['Je dois annuler une réservation ?','Utilisez « Annuler » dans « Arrivées » : le client est remboursé en totalité.'],
['Un problème avec un client ?','Ouvrez la réservation puis « Signaler un problème » : l\'équipe Avelis vous répond.'],
] as [$q, $r])
<details><summary>{{ $q }}</summary><p>{{ $r }}</p></details>
@endforeach
<a href="{{ route('contact') }}" class="btn-sec">Écrire à l'équipe</a></div></div>
