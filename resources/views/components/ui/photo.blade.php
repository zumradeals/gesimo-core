@props(['photo' => null, 'classe' => 'h-44', 'grande' => false, 'alt' => ''])
@if($photo)
    @php $mini = $photo->urlMiniature(); $pleine = $photo->url(); @endphp
    {{-- Liste : miniature légère ; fiche (grande) : image complète. Chargement différé et décodage hors du fil principal. --}}
    <img src="{{ $grande ? $pleine : $mini }}" @unless($grande || $mini === $pleine) srcset="{{ $mini }} 480w, {{ $pleine }} 1600w" sizes="(max-width: 768px) 100vw, 480px" @endunless
         alt="{{ $alt }}" loading="{{ $grande ? 'eager' : 'lazy' }}" decoding="async" width="480" height="320" class="w-full rounded-xl object-cover {{ $classe }}">
@else
    <div class="flex w-full items-center justify-center rounded-xl bg-gradient-to-br from-marque-100 to-sable-100 text-marque-600/60 {{ $classe }}"><x-ui.icone nom="photo" classe="h-10 w-10" /></div>
@endif
