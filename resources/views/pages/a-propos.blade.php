@php $nom = \App\Parametres\Services\Marque::nom(); @endphp
<div>
    <x-site.bandeau :titre="'À propos de '.$nom" sous="Une plateforme qui rend la réservation de résidences meublées simple, claire et sûre." surtitre="Qui sommes-nous" />
    <section class="sect"><div class="cont deux-col">
        <div class="bloc">
            <span class="surtitre">Notre mission</span>
            <h2>Réserver une résidence meublée en toute confiance</h2>
            <p>{{ $nom }} met en relation des voyageurs et des gérants de résidences meublées en Côte d'Ivoire. Nous vérifions les résidences avant de les publier, nous sécurisons le paiement, et nous reversons le gérant après l'arrivée du client.</p>
            <p>Le prix total est affiché avant de payer, les règles d'annulation sont claires, et chaque réservation est suivie de bout en bout.</p>
            <div style="margin-top:14px"><a href="{{ route('contact') }}" class="btn-sec">Nous écrire</a></div>
        </div>
        <div class="illus"><x-ui.logo :lien="false" /></div>
    </div></section>
    <section class="sect alt"><div class="cont">
        <div class="sect-t"><span class="surtitre">Nos engagements</span><h2>Ce que vous pouvez attendre de nous</h2></div>
        <div class="grille3">
            <div class="av"><x-ui.icone nom="bouclier" classe="ic gros" /><h3>Résidences vérifiées</h3><p>Pièces et photos contrôlées avant publication, badge « Vérifié » sur les annonces.</p></div>
            <div class="av"><x-ui.icone nom="prix" classe="ic gros" /><h3>Prix clair</h3><p>Le prix total est affiché avant de payer, sans frais cachés.</p></div>
            <div class="av"><x-ui.icone nom="carte" classe="ic gros" /><h3>Paiement sécurisé</h3><p>Wave, Orange Money, MTN Money ou carte, via un prestataire de paiement sécurisé.</p></div>
        </div>
    </div></section>
    <section class="cta-par"><div class="cont cta-in">
        <div><h2>Une question ? Parlons-en.</h2><p>Notre équipe vous répond sous 24 h (jours ouvrés).</p></div>
        <div class="cta-b"><a href="{{ route('contact') }}" class="btn-or grand">Nous contacter</a></div>
    </div></section>
</div>
