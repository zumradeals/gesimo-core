<div class="mx-auto grid max-w-md gap-4">
    <div class="carte grid gap-3 text-center">
        @if($valide)
            <h1 class="text-xl font-extrabold">Bienvenue</h1>
            <p class="text-gris-doux">Touchez le bouton pour entrer dans votre compte.</p>
            <button wire:click="entrer" class="btn btn-bloc">Me connecter</button>
        @else
            <h1 class="text-xl font-extrabold">Lien expiré</h1>
            <x-ui.alerte type="erreur">{{ $erreur ?? 'Ce lien n\'est plus valable. Demandez-en un nouveau.' }}</x-ui.alerte>
            <a href="{{ route('connexion') }}" class="btn btn-bloc">Revenir à la connexion</a>
        @endif
    </div>
</div>
