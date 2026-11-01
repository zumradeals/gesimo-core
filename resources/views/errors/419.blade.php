<x-erreur.page code="Session expirée" titre="Votre session a expiré" icone="⏱">
    <p>Pour votre sécurité, la page est restée ouverte trop longtemps. Rechargez-la pour continuer : vos réservations ne sont pas perdues.</p>
    <x-slot:actions>
        <button type="button" class="btn-pl grand pleine" onclick="location.reload()">Recharger la page</button>
        <a href="{{ route('connexion') }}" class="btn-sec pleine">Me reconnecter</a>
    </x-slot:actions>
</x-erreur.page>
