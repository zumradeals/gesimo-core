<div>
    @if($texte)
        <x-site.bandeau :titre="\App\Noyau\Livewire\PageStatique::PAGES[$page]" surtitre="Informations" />
        <section class="sect"><div class="cont"><div class="texte-legal">{!! nl2br(e($texte)) !!}</div></div></section>
    @else
        @include('pages.'.$page)
    @endif
</div>
