@php
    use App\Parametres\Services\Marque;
    $lignes = array_filter([
        'Éditeur du site' => Marque::texte('general.raison_sociale') ?? Marque::nom(),
        'Registre du commerce (RCCM)' => Marque::texte('general.rccm'),
        'Compte contribuable (NIF)' => Marque::texte('general.nif'),
        'Adresse' => Marque::texte('general.adresse'),
        'Téléphone' => Marque::texte('general.telephone'),
        'E-mail' => Marque::texte('contact.email'),
    ]);
@endphp
<div>
<x-site.bandeau titre="Mentions légales" surtitre="Informations" />
<section class="sect"><div class="cont"><div class="texte-legal">
<div class="carte-p">
    @foreach($lignes as $nom => $valeur)<div class="ligne"><span>{{ $nom }}</span><b>{{ $valeur }}</b></div>@endforeach
</div>
<p>Le paiement en ligne est assuré par un prestataire de paiement sécurisé : {{ Marque::nom() }} ne voit ni ne conserve votre numéro de carte.</p>
<p>Pour toute question, écrivez-nous depuis la page <a class="lien-fl" href="{{ route('contact') }}">Contact</a>.</p>
</div></div></section>
</div>
