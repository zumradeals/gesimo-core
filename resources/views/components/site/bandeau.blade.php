@props(['titre', 'sous' => null, 'surtitre' => null])
<section class="bandeau-page"><div class="cont">
    @if($surtitre)<span class="surtitre clair">{{ $surtitre }}</span>@endif
    <h1>{{ $titre }}</h1>
    @if($sous)<p>{{ $sous }}</p>@endif
</div></section>
