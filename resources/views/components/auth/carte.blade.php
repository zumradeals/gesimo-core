@props(['icone' => '👋', 'ton' => 'o', 'code' => null])
<div class="en-centre">
    <div {{ $attributes->class(['en-carte']) }}>
        <div class="en-rond {{ $ton }}" aria-hidden="true">{{ $icone }}</div>
        @if($code)<span class="en-code">{{ $code }}</span>@endif
        {{ $slot }}
    </div>
</div>
