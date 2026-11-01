<x-erreur.page code="Erreur 404" titre="Cette page n'existe pas" icone="🧭">
    <p>Le lien est peut-être ancien ou mal écrit. Pas de souci : vous pouvez repartir d'ici.</p>
    <x-slot:actions>
        <a href="{{ route('accueil') }}" class="btn-pl grand pleine">Aller à l'accueil</a>
        <a href="{{ route('recherche') }}" class="btn-sec pleine">Voir les logements</a>
    </x-slot:actions>
</x-erreur.page>
