<div>
    <x-site.bandeau titre="Questions fréquentes" sous="Les réponses aux questions les plus posées." surtitre="Aide" />
    <section class="sect"><div class="cont deux-col top">
        <div>@include('pages._faq')<p class="petit" style="margin-top:12px">Une autre question ? <a class="lien-fl" href="{{ route('contact') }}">Écrivez-nous</a></p></div>
        <div>@include('pages._coordonnees')</div>
    </div></section>
</div>
