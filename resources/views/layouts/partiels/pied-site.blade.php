@php
    use App\Parametres\Services\Marque;
    $tel = Marque::texte('general.telephone');
    $mail = Marque::texte('contact.email');
    $adresse = Marque::texte('general.adresse');
    $wa = Marque::lienWhatsapp();
    $reseaux = Marque::reseaux();
    $legale = Marque::ligneLegale();
    $colonnes = [
        'Explorer' => [['Les logements', route('recherche')], ['Comment ça marche', route('accueil').'#comment'], ['À propos', route('a-propos')]],
        'Vous accompagner' => [['Aide et questions fréquentes', route('faq')], ['Contact', route('contact')], ['Devenir gérant partenaire', route('devenir-partenaire')], ['Mon compte', route('compte')]],
        'Informations' => [['Conditions générales', route('conditions')], ['Politique de confidentialité', route('confidentialite')], ['Mentions légales', route('mentions-legales')]],
    ];
    $icones = [
        'facebook' => 'M14 8h3V4h-3a4 4 0 0 0-4 4v2H7v4h3v8h4v-8h3l1-4h-4V8.5A.5.5 0 0 1 14.5 8Z',
        'instagram' => 'M7 3h10a4 4 0 0 1 4 4v10a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4V7a4 4 0 0 1 4-4Zm5 5a4 4 0 1 0 0 8 4 4 0 0 0 0-8Zm5.5-1.5h.01',
        'linkedin' => 'M4 9h4v11H4zM6 3.5a2 2 0 1 1 0 4 2 2 0 0 1 0-4ZM10 9h4v1.6C14.6 9.6 15.8 9 17.3 9 20 9 21 10.7 21 13.5V20h-4v-5.8c0-1.3-.4-2.2-1.6-2.2-1.3 0-1.9.9-1.9 2.2V20h-4z',
        'tiktok' => 'M14 3v11.5a3.5 3.5 0 1 1-3.5-3.5M14 3c.3 2.5 1.8 4 4.5 4.2',
    ];
@endphp
<footer class="site-f">
    <div class="cont">
        <div class="f-haut">
            <div class="f-col f-marque">
                <x-ui.logo :clair="true" />
                <p>{{ Marque::slogan() ?: 'Des séjours meublés en Côte d’Ivoire, avec des informations claires pour choisir en confiance.' }}</p>
                <div class="f-contact">
                    @if($adresse)<div><x-ui.icone nom="pin" classe="ic" /> {{ $adresse }}</div>@endif
                    @if($tel)<div><x-ui.icone nom="tel" classe="ic" /> <a href="{{ Marque::lienTelephone($tel) }}">{{ $tel }}</a></div>@endif
                    @if($mail)<div><x-ui.icone nom="mail" classe="ic" /> <a href="mailto:{{ $mail }}">{{ $mail }}</a></div>@endif
                    @if($wa)<div><x-ui.icone nom="tel" classe="ic" /> <a href="{{ $wa }}" rel="noopener" target="_blank">WhatsApp</a></div>@endif
                </div>
                @if($reseaux)
                    <div class="f-social">
                        @foreach($reseaux as $cle => $url)
                            <a href="{{ $url }}" rel="noopener noreferrer" target="_blank" aria-label="{{ Marque::RESEAUX[$cle] }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $icones[$cle] }}"/></svg></a>
                        @endforeach
                    </div>
                @endif
            </div>
            @foreach($colonnes as $titre => $liens)
                <div class="f-col">
                    <h2 class="f-titre">{{ $titre }}</h2>
                    <div class="f-liens">@foreach($liens as [$nom, $url])<a href="{{ $url }}">{{ $nom }}</a>@endforeach</div>
                </div>
            @endforeach
        </div>
        <div class="f-paiements"><span>Moyens de paiement</span><div class="pastilles"><span>Wave</span><span>Orange Money</span><span>MTN Money</span><span>Carte</span></div><p>Les moyens disponibles sont précisés avant le paiement.</p></div>
        <div class="f-bas"><span>© {{ now()->year }} {{ Marque::nom() }} — tous droits réservés</span>@if($legale)<span>{{ $legale }}</span>@endif</div>
    </div>
</footer>
