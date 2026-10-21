@php
    $utilisateur = auth()->user();
    $roles = $utilisateur?->roles->map(fn ($r) => $r->role->libelle())->implode(', ');
    $message = $exception->getMessage() ?: "Vous n'avez pas le droit d'accéder à cette page.";
    $pourReserver = str_contains($message, 'réserver');
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
@include('layouts.partiels.tete')
</head>
<body class="site min-h-screen">
    <main class="page-courante">
        <section class="auth"><div class="cont" style="max-width:640px">
            <div class="carte-p" style="text-align:center;justify-items:center;padding:30px 22px">
                <div class="gros-ok" style="background:#fff4e5;color:#b54708"><x-ui.icone nom="alerte" classe="ic" /></div>
                <h1 style="font-size:26px">Cette page n'est pas accessible avec ce compte</h1>
                <p style="margin:0">{{ $message }}</p>
                @if($utilisateur)
                    <p class="petit" style="margin:0">Vous êtes connecté comme <b>{{ $utilisateur->nom }}</b> ({{ $roles }}).</p>
                    @if($pourReserver)
                        <p class="petit" style="margin:0">Pour réserver, connectez-vous avec un compte client (ou créez-en un avec un autre téléphone et une autre adresse e-mail). Un gérant qui prend une réservation par téléphone passe par son calendrier.</p>
                    @endif
                @endif
                <div style="display:grid;gap:10px;width:100%;margin-top:6px">
                    @if($utilisateur && $pourReserver)
                        <form method="POST" action="{{ route('deconnexion') }}">@csrf<button class="btn-pl grand pleine">Me déconnecter pour utiliser un compte client</button></form>
                    @endif
                    <a href="{{ $utilisateur ? route('compte') : route('connexion') }}" class="btn-sec pleine">{{ $utilisateur ? 'Retour à mon espace' : 'Se connecter' }}</a>
                    <a href="{{ route('recherche') }}" class="lien-fl" style="justify-content:center">Voir les logements <x-ui.icone nom="fleche" classe="ic" /></a>
                </div>
            </div>
        </div></section>
    </main>
</body>
</html>
