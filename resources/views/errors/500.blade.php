{{-- Page statique : aucune donnée, aucune session, aucun réglage (elle doit s'afficher même si la base est en panne). --}}
<x-erreur.page code="Erreur 500" titre="Un problème de notre côté" icone="⚠" ton="r" :statique="true">
    <p>Nous n'avons pas pu afficher cette page. Réessayez dans un instant. Si cela continue, écrivez-nous.</p>
    <x-slot:actions>
        <button type="button" class="btn-pl grand pleine" onclick="location.reload()">Réessayer</button>
        <a href="/contact" class="btn-sec pleine">Nous contacter</a>
    </x-slot:actions>
</x-erreur.page>
