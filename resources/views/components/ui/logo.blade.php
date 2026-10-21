@props(['clair' => false, 'lien' => true])
@php [$premier, $reste] = \App\Parametres\Services\Marque::nomEnDeux(); $logo = \App\Parametres\Services\Marque::logoUrl(); @endphp
<{{ $lien ? 'a href='.route('accueil') : 'span' }} class="logo {{ $clair ? 'clair' : '' }}">
    @if($logo)<img src="{{ $logo }}" alt="" width="34" height="34">@else<span class="logo-g" aria-hidden="true">{{ mb_strtoupper(mb_substr($premier, 0, 1)) }}</span>@endif
    <span>{{ $premier }}@if($reste) <b>{{ $reste }}</b>@endif</span>
</{{ $lien ? 'a' : 'span' }}>
