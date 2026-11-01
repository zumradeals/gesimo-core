@if($valide)
    <x-auth.carte icone="👋">
        <h1>Bienvenue</h1>
        <p>Touchez le bouton pour entrer dans votre compte.</p>
        <div class="en-actions"><button wire:click="entrer" class="btn btn-bloc">Me connecter</button></div>
        <p class="en-aide">Ce lien ne marche qu'une fois.</p>
    </x-auth.carte>
@else
    <x-auth.carte icone="⏱" ton="r">
        <h1>Lien expiré</h1>
        <x-ui.alerte type="erreur">{{ $erreur ?? 'Ce lien n\'est plus valable. Demandez-en un nouveau.' }}</x-ui.alerte>
        <div class="en-actions"><a href="{{ route('connexion') }}" class="btn btn-bloc">Revenir à la connexion</a></div>
    </x-auth.carte>
@endif
