@props(['photo' => null, 'classe' => 'h-44'])
@if($photo)
    <img src="{{ $photo->url() }}" alt="" loading="lazy" class="w-full rounded-xl object-cover {{ $classe }}">
@else
    <div class="flex w-full items-center justify-center rounded-xl bg-gradient-to-br from-marque-100 to-sable-100 text-marque-600/60 {{ $classe }}"><x-ui.icone nom="photo" classe="h-10 w-10" /></div>
@endif
