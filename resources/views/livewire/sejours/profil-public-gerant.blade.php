<div class="cont pg-public">
    @if($apercu)<x-ui.alerte type="info">Aperçu de votre profil public. Seules vos offres publiées sont présentées. <a class="underline" href="{{ route('gerant.profil') }}">Modifier mon profil</a></x-ui.alerte>@endif
    <p class="fil"><a href="{{ route('accueil') }}">Accueil</a> › Votre hébergeur › {{ $nomPublic }}</p>
    <div class="pg-public-hero">
        @if($logoChemin)<img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($logoChemin) }}" alt="{{ $nomPublic }}" width="96" height="96" class="pg-avatar">@else<span class="pg-avatar" aria-hidden="true">{{ mb_substr($nomPublic, 0, 1) }}</span>@endif
        <div><span class="surtitre">Votre hébergeur en Côte d’Ivoire</span><h1>{{ $nomPublic }}</h1><div class="flex flex-wrap items-center gap-3">@if($certifie)<span class="pg-badge or">✓ Gérant certifié</span>@endif @if($communePublique)<span>{{ $communePublique }}</span>@endif<span>{{ $unites->total() }} offre(s) publiée(s)</span></div></div>
    </div>
    <div class="pg-public-intro"><div><h2>À propos de votre hébergeur</h2>@if($presentationPublique)<p class="whitespace-pre-line">{{ $presentationPublique }}</p>@else<p>Découvrez les logements proposés par {{ $nomPublic }} sur Avelis Séjours.</p>@endif</div>@if($certifie)<aside><h3>Que signifie « Gérant certifié » ?</h3><p>Avelis a contrôlé la pièce d’identité et le certificat de résidence de ce gérant. Le badge de gestion de chaque résidence fait l’objet d’un contrôle séparé.</p></aside>@endif</div>
    <h2>Les séjours proposés par {{ $nomPublic }}</h2>
    <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3 mt-5">
        @forelse($unites as $u)
            @php $r = $u->residence; $photo = $u->photos->first() ?? $r->photos->first(); $prix = $u->tarifs->firstWhere('type_periode', 'standard'); @endphp
            <article class="lg">
                <a href="{{ route('logement', $u) }}" class="lg-photo">@if($photo)<img src="{{ $photo->urlMiniature() }}" alt="{{ $u->nom }}" width="480" height="320" loading="lazy" class="h-full w-full object-cover">@else<x-ui.icone nom="photo" classe="h-10 w-10" />@endif</a>
                <div class="lg-c">@if($r->aBadgeGestion())<span class="pg-badge w-fit">✓ Droit de gestion vérifié</span>@endif<h3>{{ $u->nom }}</h3><p class="lg-l">{{ $r->commune }} · {{ $u->capacite }} voyageur(s)</p><p class="lg-p"><b>{{ \App\Noyau\Services\Format::fcfa($prix->prix_nuit) }}</b> <small>/ nuit</small></p><a class="lien-fl" href="{{ route('logement', $u) }}">Découvrir ce logement →</a></div>
            </article>
        @empty
            <p class="aide">Vos offres apparaîtront ici après leur publication.</p>
        @endforelse
    </div>
    <div class="mt-6">{{ $unites->links() }}</div>
</div>
