@component('emails.gabarit', ['titre' => 'Votre lien de connexion', 'bouton' => ['texte' => 'Me connecter', 'lien' => $lien], 'note' => "Si vous n'avez rien demandé, ignorez ce message : personne ne peut entrer sans ce lien."])
<p style="margin:0 0 14px">Bonjour {{ $nom }},</p>
<p style="margin:0 0 14px">Voici votre lien pour vous connecter à Avelis Séjours. Il ne marche qu'une seule fois, pendant {{ $minutes }} minutes.</p>
@endcomponent
