@props(['titre', 'sections' => []])
<div>
<x-site.bandeau :titre="$titre" surtitre="Informations" />
<section class="sect"><div class="cont pi-lecture">
    @if($sections)
        <nav class="pi-sommaire" aria-label="Sommaire"><b class="surtitre">Sur cette page</b>
            @foreach($sections as $id => $libelle)<a href="#{{ $id }}">{{ $libelle }}</a>@endforeach
        </nav>
    @endif
    <div class="texte-legal">{{ $slot }}</div>
</div></section>
</div>
