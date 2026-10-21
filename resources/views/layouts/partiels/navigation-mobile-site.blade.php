<nav class="barre-bas mobile-tabs" aria-label="Navigation mobile">
    @foreach(\App\Noyau\Services\Navigation::visiteur($utilisateur) as $m)
        <a href="{{ route($m['route']) }}" class="{{ $estActif($m) ? 'on' : '' }}" @if($estActif($m)) aria-current="page" @endif><span class="tab-icone"><x-ui.icone :nom="$m['icone']" /></span><span>{{ $m['libelle'] }}</span></a>
    @endforeach
</nav>
