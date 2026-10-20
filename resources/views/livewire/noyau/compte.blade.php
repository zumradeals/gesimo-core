<div class="mx-auto grid max-w-xl gap-4">
    <div class="carte grid gap-2">
        <h1 class="text-xl font-extrabold">{{ $utilisateur->nom }}</h1>
        <p class="text-gris-doux">{{ \App\Noyau\Services\Telephone::afficher($utilisateur->telephone) }} · {{ $utilisateur->email }}</p>
        <div class="flex flex-wrap gap-2">
            @foreach($utilisateur->roles as $r)
                <span class="rounded-full bg-marque-50 px-3 py-0.5 text-[13px] font-bold text-marque-600">{{ $r->role->libelle() }}</span>
            @endforeach
        </div>
    </div>
    @if($utilisateur->aRole(\App\Noyau\Enums\Role::Gerant))
        <a href="{{ route('gerant.personnel') }}" class="btn btn-second btn-bloc"><x-ui.icone nom="equipe" classe="h-5 w-5" /> Mon personnel</a>
    @endif
    <form method="POST" action="{{ route('deconnexion') }}">@csrf
        <button class="btn btn-danger btn-bloc"><x-ui.icone nom="sortie" classe="h-5 w-5" /> Se déconnecter</button>
    </form>
</div>
