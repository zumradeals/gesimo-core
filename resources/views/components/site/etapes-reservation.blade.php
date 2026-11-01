@props(['etape' => 1])
@php $noms = [1 => 'Vos informations', 2 => 'Paiement', 3 => 'Confirmation']; @endphp
<ol class="rv-etapes" aria-label="Étapes de la réservation">
    @foreach($noms as $n => $nom)
        <li class="{{ $n < $etape ? 'fait' : ($n === $etape ? 'on' : '') }}" @if($n === $etape) aria-current="step" @endif><i>{{ $n < $etape ? '✓' : $n }}</i><span>{{ $nom }}</span></li>
        @if($n < 3)<li class="trait" aria-hidden="true"></li>@endif
    @endforeach
</ol>
