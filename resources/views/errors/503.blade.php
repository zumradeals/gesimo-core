{{-- Page statique de maintenance : même contrainte que la page 500. --}}
<x-erreur.page code="Maintenance" titre="Nous revenons très vite" icone="🛠" :statique="true">
    <p>Avelis Séjours est en cours de mise à jour. Vos réservations sont conservées. Réessayez dans quelques minutes.</p>
    <x-slot:actions>
        <button type="button" class="btn-pl grand pleine" onclick="location.reload()">Réessayer</button>
    </x-slot:actions>
</x-erreur.page>
