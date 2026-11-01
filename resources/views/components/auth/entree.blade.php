@props(['titre', 'texte' => null, 'points' => []])
@php
    $image = \App\Parametres\Services\Marque::imageAccueilUrl() ?: asset('images/sejours/salon-balcon.webp');
@endphp
<div class="en-entree">
    <aside class="en-panneau">
        <h2>{{ $titre }}</h2>
        @if($texte)<p>{{ $texte }}</p>@endif
        <ul class="en-points">@foreach($points as $point)<li><i aria-hidden="true">✓</i>{{ $point }}</li>@endforeach</ul>
        <img class="en-photo" src="{{ $image }}" alt="" width="1536" height="1024" loading="lazy" decoding="async">
    </aside>
    <div class="en-formu">{{ $slot }}</div>
</div>
