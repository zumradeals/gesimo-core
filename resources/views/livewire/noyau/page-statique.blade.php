<div>
    @if($texte)
        <div class="carte grid gap-2 md:max-w-3xl">
            <h1 class="text-2xl font-extrabold">{{ \App\Noyau\Livewire\PageStatique::PAGES[$page] }}</h1>
            <div class="leading-relaxed">{!! nl2br(e($texte)) !!}</div>
        </div>
    @else
        @include('pages.'.$page)
    @endif
</div>
