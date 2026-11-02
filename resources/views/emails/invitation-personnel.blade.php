@component('emails.gabarit', ['titre' => 'Vous êtes invité', 'bouton' => ['texte' => 'Créer mon accès', 'lien' => $lien], 'note' => "Ce lien est valable 7 jours. Si vous ne connaissez pas cette personne, ignorez ce message."])
<p style="margin:0 0 14px">Bonjour {{ $nom }},</p>
<p style="margin:0 0 14px">{{ $gerant }} vous invite à rejoindre l'équipe de la résidence <b>{{ $residence }}</b> sur Avelis Séjours.</p>
@endcomponent
