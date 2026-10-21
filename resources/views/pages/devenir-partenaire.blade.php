@php
    use App\Parametres\Services\Marque;
    $nom = Marque::nom();
    $inscription = route('inscription', ['profil' => 'gerant']);
    $taux = \App\Paiement\Services\ReglagesPaiement::commissionPourcentage();
    $exemple = 50000;
    $commission = $taux !== null ? (int) round($exemple * $taux / 100, 0, PHP_ROUND_HALF_UP) : null;
    $fr = fn (int $m) => \App\Noyau\Services\Format::fcfa($m);
@endphp
<div class="partenaire-v2">

<div class="pa-hero"><img src="{{ asset('images/sejours/appartement.webp') }}" alt="" width="768" height="512" fetchpriority="high" decoding="async">
<div class="pa-cont"><span class="pa-eyebrow">Gérants de résidences meublées</span>
<h1>Votre résidence mérite<br>d’être choisie.</h1>
<p>Présentez vos logements aux voyageurs, organisez vos disponibilités et suivez vos réservations avec {{ $nom }}.</p>
<div class="pa-actions">
<a class="pa-btn pa-or" href="{{ $inscription }}">Devenir gérant partenaire <span>→</span>
</a>
<a class="pa-lien" href="{{ route('contact') }}">Échanger avec notre équipe ↗</a>
</div><small>Inscription gratuite · Identité contrôlée avant publication</small>
</div>
</div>
<div class="pa-cont pa-preuves">
<div class="pa-preuve"><span class="pa-icone"><x-ui.icone nom="batiment" classe="ic" /></span>
<div><b>Vos logements, votre espace</b>
<p>Annonces et disponibilités réunies.</p>
</div>
</div>
<div class="pa-preuve"><span class="pa-icone"><x-ui.icone nom="carte" classe="ic" /></span>
<div><b>Des paiements suivis</b>
<p>Mobile Money et carte selon les moyens disponibles.</p>
</div>
</div>
<div class="pa-preuve"><span class="pa-icone"><x-ui.icone nom="bouclier" classe="ic" /></span>
<div><b>Un cadre clair pour démarrer</b>
<p>Contrôle du dossier et conditions expliquées.</p>
</div>
</div>
</div>
<section>
<div class="pa-cont">
<div class="pa-intro"><span class="pa-eyebrow">Faites grandir votre activité</span>
<h2>Plus de visibilité.<br>Une gestion plus sereine.</h2>
<p>Du premier contact à l’arrivée du voyageur, retrouvez les informations utiles pour accueillir dans de bonnes conditions.</p>
</div>
<div class="pa-grille">
<article class="pa-carte"><span class="pa-icone"><x-ui.icone nom="fleche" classe="ic" /></span>
<h3>Présentez ce qui vous distingue</h3>
<p>Photos, équipements, tarifs et règlement : donnez aux voyageurs les repères pour choisir votre logement.</p>
</article>
<article class="pa-carte"><span class="pa-icone"><x-ui.icone nom="calendrier" classe="ic" /></span>
<h3>Gardez la main sur votre calendrier</h3>
<p>Suivez les séjours et renseignez aussi vos réservations prises par téléphone pour tenir vos disponibilités à jour.</p>
</article>
<article class="pa-carte"><span class="pa-icone"><x-ui.icone nom="cle" classe="ic" /></span>
<h3>Préparez chaque arrivée</h3>
<p>Retrouvez les réservations confirmées et les informations nécessaires pour organiser l’accueil de vos clients.</p>
</article>
</div>
</div>
</section>
<section class="pa-outil">
<div class="pa-cont pa-deux">
<div><span class="pa-eyebrow">Votre quotidien, simplifié</span>
<h2>Un espace pensé<br>pour les gérants.</h2>
<p>Vos résidences et vos séjours restent accessibles depuis votre téléphone comme depuis votre ordinateur.</p>
<div class="pa-liste">
<div><b>Annonces et disponibilités</b>
<p>Présentez vos logements et suivez votre calendrier.</p>
</div>
<div><b>Réservations et arrivées</b>
<p>Retrouvez les séjours confirmés et préparez l’accueil.</p>
</div>
<div><b>Paiements et reversements</b>
<p>Consultez les montants et leur état dans votre espace.</p>
</div>
</div>
</div>
<div class="pa-espace">
<div class="pa-espace-tete"><span>{{ $nom }}</span><span>Espace gérant</span>
</div>
<div class="pa-espace-corps">
<p>Les outils qui accompagnent votre activité</p>
<div class="pa-ligne">
<div><b>Mes résidences</b><small>Photos, équipements et tarifs</small>
</div><span class="pa-pastille">Présenter</span>
</div>
<div class="pa-ligne">
<div><b>Mon calendrier</b><small>Disponibilités et séjours</small>
</div><span class="pa-pastille">Organiser</span>
</div>
<div class="pa-ligne">
<div><b>Mes réservations</b><small>Suivi et préparation des arrivées</small>
</div><span class="pa-pastille">Accueillir</span>
</div>
<div class="pa-ligne">
<div><b>Mon argent</b><small>Paiements et reversements</small>
</div><span class="pa-pastille">Suivre</span>
</div>
</div>
</div>
</div>
</section>
<section id="demarrer">
<div class="pa-cont">
<div class="pa-intro"><span class="pa-eyebrow">Rejoindre {{ $nom }}</span>
<h2>De votre dossier<br>à vos premières annonces.</h2>
<p>Un parcours progressif, avec vos pièces personnelles enregistrées dans votre profil gérant.</p>
</div>
<div class="pa-grille pa-parcours">
<article class="pa-etape"><span class="pa-num">01</span>
<h3>Créez votre profil</h3>
<p>Renseignez vos coordonnées et déposez votre pièce d’identité dans votre espace personnel.</p>
</article>
<article class="pa-etape"><span class="pa-num">02</span>
<h3>Présentez votre résidence</h3>
<p>Ajoutez les logements, les photos, les équipements, les tarifs et les conditions du séjour.</p>
</article>
<article class="pa-etape"><span class="pa-num">03</span>
<h3>Soumettez pour validation</h3>
<p>Avelis contrôle votre dossier avant publication. Vos annonces validées peuvent ensuite être réservées.</p>
</article>
</div>
</div>
</section>
<section class="pa-confiance" id="engagements">
<div class="pa-cont">
<div class="pa-intro"><span class="pa-eyebrow">La confiance, avec des repères précis</span>
<h2>Un contrôle pour publier.<br>Des badges pour aller plus loin.</h2>
<p>Chaque reconnaissance correspond à un justificatif précis. Les badges complémentaires restent facultatifs.</p>
</div>
<div class="pa-grille">
<article class="pa-carte"><span class="pa-etiquette pa-obligatoire">Obligatoire pour publier</span>
<h3>Identité validée</h3>
<p>Votre pièce d’identité est contrôlée avant la publication de vos résidences.</p>
</article>
<article class="pa-carte"><span class="pa-etiquette">Badge gérant facultatif</span>
<h3>Gérant certifié</h3>
<p>En complément de votre identité, fournissez un certificat de résidence pour faire valider ce badge.</p>
</article>
<article class="pa-carte"><span class="pa-etiquette">Badge résidence facultatif</span>
<h3>Gestion vérifiée</h3>
<p>Un justificatif du droit de gestion contrôlé permet d’attribuer ce badge à la résidence concernée.</p>
</article>
</div>
</div>
</section>
<section class="pa-tarifs">
<div class="pa-cont pa-deux">
<div><span class="pa-eyebrow">Des conditions compréhensibles</span>
<h2>Vous savez<br>comment vous êtes payé.</h2>
<p>{{ $nom }} retient une commission sur les réservations payées en ligne<span>@if($taux !== null) de <b>{{ rtrim(rtrim(number_format($taux, 2, ',', ''), '0'), ',') }} %</b>@endif</span>. Les paiements et reversements se suivent depuis votre espace gérant.</p>
</div>
<div class="pa-carte">
<h3>Les principes à connaître</h3>
<div class="pa-liste">
<div><b>Inscription gratuite</b>
<p>Créez votre compte et préparez votre dossier.</p>
</div>
<div><b>Commission sur les réservations en ligne</b>
<p>Les réservations prises par téléphone et enregistrées dans votre espace sont sans commission.</p>
</div>
<div><b>Reversement après l’arrivée</b>
<p>Suivez les montants et leur état dans votre espace.</p>
</div>
@if($commission !== null)
<div class="pa-exemple" aria-label="Exemple chiffré"><b>Exemple sur une réservation de {{ $fr($exemple) }}</b>
<div><span>Commission</span><b>{{ $fr($commission) }}</b>
</div>
<div><span>Vous recevez</span><b>{{ $fr($exemple - $commission) }}</b>
</div>
<p>Exemple indicatif : le montant exact dépend de votre réservation.</p>
</div>
@endif
</div>
</div>
</div>
</section>
<section id="aide">
<div class="pa-cont pa-deux">
<div><span class="pa-eyebrow">Nous vous accompagnons</span>
<h2>Vos questions<br>avant de vous lancer.</h2>
<p>Comprendre le fonctionnement avant de publier, c’est préparer une relation durable avec vos voyageurs.</p>
</div>
<div class="pa-faq">
<details>
<summary>Dois-je renvoyer mon identité pour chaque résidence ?</summary>
<p>Vos pièces personnelles sont enregistrées dans votre profil gérant. Elles accompagnent vos résidences selon leur état de validation.</p>
</details>
<details>
<summary>Le certificat de résidence est-il obligatoire ?</summary>
<p>Non. Il complète votre identité pour obtenir le badge Gérant certifié. Il n’est pas une condition de publication.</p>
</details>
<details>
<summary>Puis-je gérer plusieurs résidences ?</summary>
<p>Oui. Votre espace permet de présenter et de suivre plusieurs résidences depuis le même compte gérant.</p>
</details>
<details>
<summary>Puis-je enregistrer mes réservations téléphoniques ?</summary>
<p>Oui. Enregistrez-les dans votre calendrier afin de mettre à jour les disponibilités de vos logements.</p>
</details>
</div>
</div>
</section>
<section class="pa-rejoindre" id="rejoindre">
<div class="pa-cont">
<div class="pa-appel">
<div>
<h2>Faisons découvrir<br>votre prochaine adresse.</h2>
<p>Créez votre profil et préparez votre première résidence. Votre activité mérite une présentation à sa hauteur.</p>
</div>
<a class="pa-btn" href="{{ $inscription }}">Créer mon compte gérant <span>→</span>
</a>
</div>
</div>
</section>
</div>
