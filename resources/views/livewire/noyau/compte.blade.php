@php $estClient = $utilisateur->aRole(\App\Noyau\Enums\Role::Client); $estGerant = $utilisateur->aRole(\App\Noyau\Enums\Role::Gerant); @endphp
<div class="grid gap-4">
    <div class="rounded-2xl bg-gradient-to-br from-marque-600 to-marque-500 p-5 text-white lg:p-7">
        <h1 class="text-2xl font-extrabold">Bonjour {{ \Illuminate\Support\Str::of($utilisateur->nom)->before(' ') }} 👋</h1>
        <p class="opacity-95">{{ \App\Noyau\Services\Telephone::afficher($utilisateur->telephone) }} · {{ $utilisateur->email }}</p>
        <div class="mt-2 flex flex-wrap gap-2">@foreach($utilisateur->roles as $r)<span class="rounded-full bg-white/20 px-3 py-0.5 text-[13px] font-bold">{{ $r->role->libelle() }}</span>@endforeach</div>
    </div>

    @if($estClient)
    <div class="grid gap-4 md:grid-cols-3">
        <div class="carte md:col-span-2 grid gap-1">
            <h2 class="text-lg font-extrabold">Votre prochain séjour</h2>
            @if($prochain)
                <p class="text-lg font-bold">{{ $prochain->unite->nom }} · {{ $prochain->unite->residence->nom }}</p>
                <p class="aide">Arrivée le {{ $prochain->date_arrivee->translatedFormat('l j F') }} · gérant : {{ $prochain->unite->residence->gerant->nom }} ({{ \App\Noyau\Services\Telephone::afficher($prochain->unite->residence->gerant->telephone) }})</p>
                <a href="{{ route('reservation.detail', $prochain) }}" class="btn btn-bloc mt-1 md:w-auto">Voir ma réservation</a>
            @else
                <p class="text-gris-doux">Vous n'avez pas encore de séjour à venir. Trouvez votre prochain logement.</p>
                <a href="{{ route('accueil') }}" class="btn btn-bloc mt-1 md:w-auto">Chercher un logement</a>
            @endif
        </div>
        <div class="grid grid-cols-2 gap-3 md:grid-cols-1">
            <div class="carte"><b class="text-2xl">{{ $nombreSejours }}</b><p class="aide">séjour(s) terminé(s)</p></div>
            <div class="carte"><b class="text-2xl">{{ $nombreAVenir }}</b><p class="aide">à venir</p></div>
        </div>
    </div>
    @if($avisADonner->isNotEmpty())
    <div class="carte grid gap-1"><h2 class="text-lg font-extrabold">À faire</h2>
        @foreach($avisADonner as $a)<div class="flex items-center justify-between gap-2 border-b border-dashed border-trait py-2 last:border-0"><span>⭐ Donner votre avis sur « {{ $a->unite->nom }} »</span><a class="font-bold text-marque-600 underline" href="{{ route('reservation.avis', $a) }}">Donner</a></div>@endforeach
    </div>
    @endif
    @endif

    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
        <a href="{{ route('profil') }}" class="btn btn-second btn-bloc"><x-ui.icone nom="compte" classe="h-5 w-5" /> Mon profil</a>
        @if($estGerant)
            <a href="{{ route('gerant.personnel') }}" class="btn btn-second btn-bloc"><x-ui.icone nom="equipe" classe="h-5 w-5" /> Mon personnel</a>
            <a href="{{ route('gerant.aide') }}" class="btn btn-second btn-bloc"><x-ui.icone nom="aide" classe="h-5 w-5" /> Aide du gérant</a>
            <span class="btn btn-bloc cursor-default !bg-slate-200 !text-slate-500 hover:!bg-slate-200">⭐ Mise en avant : bientôt</span>
        @endif
        <a href="{{ route('faq') }}" class="btn btn-second btn-bloc"><x-ui.icone nom="aide" classe="h-5 w-5" /> Aide et questions</a>
        <form method="POST" action="{{ route('deconnexion') }}">@csrf
            <button class="btn btn-danger btn-bloc"><x-ui.icone nom="sortie" classe="h-5 w-5" /> Se déconnecter</button>
        </form>
    </div>
</div>
