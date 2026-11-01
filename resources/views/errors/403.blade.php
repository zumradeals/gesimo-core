@php
    $utilisateur = auth()->user();
    $roles = $utilisateur?->roles->map(fn ($r) => $r->role->libelle())->implode(', ');
    $message = $exception->getMessage() ?: "Vous n'avez pas le droit d'accéder à cette page.";
    $pourReserver = str_contains($message, 'réserver');
@endphp
<x-erreur.page code="Erreur 403" titre="Cette page n'est pas accessible avec ce compte" icone="🔒">
    <p>{{ $message }}</p>
    @if($utilisateur)
        <p class="en-aide">Vous êtes connecté comme <b>{{ $utilisateur->nom }}</b> ({{ $roles }}).</p>
        @if($pourReserver)
            <p class="en-aide">Pour réserver, connectez-vous avec un compte client (ou créez-en un avec un autre téléphone et une autre adresse e-mail). Un gérant qui prend une réservation par téléphone passe par son calendrier.</p>
        @endif
    @endif
    <x-slot:actions>
        @if($utilisateur && $pourReserver)
            <form method="POST" action="{{ route('deconnexion') }}">@csrf<button class="btn-pl grand pleine">Me déconnecter pour utiliser un compte client</button></form>
        @endif
        <a href="{{ $utilisateur ? route('compte') : route('connexion') }}" class="{{ $utilisateur && $pourReserver ? 'btn-sec' : 'btn-pl grand' }} pleine">{{ $utilisateur ? 'Retour à mon espace' : 'Se connecter' }}</a>
        <a href="{{ route('recherche') }}" class="btn-sec pleine">Voir les logements</a>
    </x-slot:actions>
</x-erreur.page>
