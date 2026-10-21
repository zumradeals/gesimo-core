@php
    use App\Parametres\Services\Marque;
    $tel = Marque::texte('general.telephone'); $mail = Marque::texte('contact.email'); $adresse = Marque::texte('general.adresse');
    $wa = Marque::lienWhatsapp(); $horaires = Marque::texte('general.horaires');
@endphp
<div class="carte-p">
    <h2 style="margin:0;font-size:18px">Nos coordonnées</h2>
    <div class="coord">
        @if($tel)<div><x-ui.icone nom="tel" classe="ic" /><span>Téléphone<br><a href="{{ Marque::lienTelephone($tel) }}">{{ $tel }}</a></span></div>@endif
        @if($wa)<div><x-ui.icone nom="tel" classe="ic" /><span>WhatsApp<br><a href="{{ $wa }}" rel="noopener" target="_blank">Nous écrire sur WhatsApp</a></span></div>@endif
        @if($mail)<div><x-ui.icone nom="mail" classe="ic" /><span>E-mail<br><a href="mailto:{{ $mail }}">{{ $mail }}</a></span></div>@endif
        @if($adresse)<div><x-ui.icone nom="pin" classe="ic" /><span>Adresse<br>{{ $adresse }}</span></div>@endif
        <div><x-ui.icone nom="horloge" classe="ic" /><span>Réponse<br>sous 24 h (jours ouvrés)@if($horaires)<br>{{ $horaires }}@endif</span></div>
    </div>
    <p class="petit">Un problème avec une réservation ? Ouvrez-la depuis « Mes réservations » puis « Signaler un problème ».</p>
</div>
