@php
    $taux = \App\Paiement\Services\ReglagesPaiement::commissionPourcentage();
    $exemple = 50000;
    $commission = $taux !== null ? (int) round($exemple * $taux / 100, 0, PHP_ROUND_HALF_UP) : null;
    $fr = fn (int $m) => \App\Noyau\Services\Format::fcfa($m);
@endphp
<div>
    <section class="hero"><div class="cont hero-in">
        <div class="hero-txt">
            <span class="surtitre clair">Gérants de résidences meublées</span>
            <h1>Gérez vos résidences avec {{ \App\Parametres\Services\Marque::nom() }}</h1>
            <p>Plus de réservations, paiements sécurisés, calendrier simple et reversement après l'arrivée du client.</p>
            <div class="h-btn"><a href="{{ route('inscription', ['profil' => 'gerant']) }}" class="btn-or grand">Créer mon compte gérant</a><a href="{{ route('contact') }}" class="lien-clair">Une question ? Écrivez-nous <x-ui.icone nom="fleche" classe="ic" /></a></div>
        </div>
        <div class="recherche">
            <h2>Ce que vous gagnez</h2>
            <ul class="garanties">
                <li><x-ui.icone nom="ok" classe="ic" /> Visibilité auprès de voyageurs qui cherchent</li>
                <li><x-ui.icone nom="ok" classe="ic" /> Paiement en ligne sécurisé (Mobile Money, carte)</li>
                <li><x-ui.icone nom="ok" classe="ic" /> Calendrier simple, sans double réservation</li>
                <li><x-ui.icone nom="ok" classe="ic" /> Reversement après l'arrivée du client</li>
                <li><x-ui.icone nom="ok" classe="ic" /> Inscription gratuite</li>
            </ul>
        </div>
    </div></section>
    <section class="sect"><div class="cont">
        <div class="sect-t"><span class="surtitre">Pour démarrer</span><h2>Trois étapes</h2></div>
        <div class="etapes-par">
            <div class="etape"><span class="num">1</span><h3>Créez votre compte</h3><p>Téléphone et e-mail, en 2 minutes.</p></div>
            <div class="etape"><span class="num">2</span><h3>Ajoutez votre résidence</h3><p>Pièce d’identité dans votre profil, puis photos, prix et règlement de la résidence. Les justificatifs complémentaires sont facultatifs.</p></div>
            <div class="etape"><span class="num">3</span><h3>Nous vérifions</h3><p>Puis vos logements apparaissent et reçoivent des réservations.</p></div>
        </div>
    </div></section>
    <section class="sect alt"><div class="cont deux-col top">
        <div class="bloc">
            <span class="surtitre">Tarifs</span>
            <h2>Combien ça coûte ?</h2>
            <p>{{ \App\Parametres\Services\Marque::nom() }} retient une commission sur chaque réservation payée en ligne @if($taux !== null)(<b>{{ rtrim(rtrim(number_format($taux, 2, ',', ''), '0'), ',') }} %</b>)@endif. Les réservations prises par téléphone sont gratuites.</p>
        </div>
        @if($commission !== null)
            <div class="exemple" aria-label="Exemple chiffré">
                <b>Exemple sur une réservation de {{ $fr($exemple) }}</b>
                <div class="ligne"><span>Commission ({{ rtrim(rtrim(number_format($taux, 2, ',', ''), '0'), ',') }} %)</span><b>{{ $fr($commission) }}</b></div>
                <div class="ligne"><span>Vous recevez</span><b>{{ $fr($exemple - $commission) }}</b></div>
                <p class="petit">Exemple indicatif : le montant exact dépend de votre réservation.</p>
            </div>
        @endif
    </div></section>
    <section class="cta-par"><div class="cont cta-in">
        <div><h2>Prêt à recevoir plus de réservations ?</h2><p>Créez votre compte gérant gratuitement.</p></div>
        <div class="cta-b"><a href="{{ route('inscription', ['profil' => 'gerant']) }}" class="btn-or grand">Créer mon compte gérant</a></div>
    </div></section>
</div>
