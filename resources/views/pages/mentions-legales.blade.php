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
<x-site.lecture titre="Mentions légales" :sections="['sec-editeur' => 'Éditeur du site', 'sec-paiement' => 'Paiement en ligne', 'sec-contact' => 'Contact']">
<div class="carte-p" id="sec-editeur">
    @foreach($lignes as $nom => $valeur)<div class="ligne"><span>{{ $nom }}</span><b>{{ $valeur }}</b></div>@endforeach
</div>
<p id="sec-paiement">Le paiement en ligne est assuré par un prestataire de paiement sécurisé : {{ Marque::nom() }} ne voit ni ne conserve votre numéro de carte.</p>
<p id="sec-contact">Pour toute question, écrivez-nous depuis la page <a class="lien-fl" href="{{ route('contact') }}">Contact</a>.</p>
</x-site.lecture>
