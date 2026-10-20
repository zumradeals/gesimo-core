# Mesures Lighthouse (version 13.5.0, mobile) — Lot 6

Mesurées le 20 octobre 2026 sur le site compilé en mode production (APP_ENV=production, APP_DEBUG=false), derrière un relais qui compresse en gzip comme le fera Nginx. Téléphone simulé (Moto G Power, processeur ralenti 4×).
- **4G** : réglage par défaut de Lighthouse (1,6 Mbit/s, 150 ms).
- **3G lente** : 400 kbit/s, 400 ms de latence (le pire cas courant).

| Page | Réseau | Performance | Accessibilité | Bonnes pratiques | SEO | 1er affichage (FCP) | Contenu principal (LCP) | Poids |
|---|---|---|---|---|---|---|---|---|
| accueil | 3G lente | 94 | 100 | 100 | 91 | 2.5 s | 2.5 s | 114 KiB |
| accueil | 4G | 99 | 100 | 100 | 91 | 0.9 s | 0.9 s | 114 KiB |
| connexion | 3G lente | 93 | 100 | 100 | 91 | 2.5 s | 2.5 s | 114 KiB |
| connexion | 4G | 100 | 100 | 100 | 91 | 1.0 s | 1.0 s | 114 KiB |
| faq | 3G lente | 94 | 100 | 100 | 91 | 2.5 s | 2.5 s | 114 KiB |
| faq | 4G | 100 | 100 | 100 | 91 | 1.0 s | 1.0 s | 114 KiB |
| logement | 3G lente | 70 | 100 | 100 | 91 | 3.9 s | 5.3 s | 115 KiB |
| logement | 4G | 99 | 100 | 100 | 91 | 1.0 s | 1.0 s | 115 KiB |
| mes-reservations | 3G lente | 94 | 100 | 100 | 91 | 2.5 s | 2.5 s | 114 KiB |
| mes-reservations | 4G | 100 | 100 | 100 | 91 | 1.0 s | 1.0 s | 114 KiB |
| recherche | 3G lente | 93 | 100 | 100 | 91 | 2.5 s | 2.5 s | 114 KiB |
| recherche | 4G | 100 | 100 | 100 | 91 | 1.0 s | 1.0 s | 114 KiB |

Note : une première mesure de « logement » en 3G lente a donné 5,3 s (valeur aberrante) ; deux nouvelles mesures : 2,5 s et 2,5 s.
Lighthouse 13 n'a plus de catégorie « PWA » : l'installabilité est vérifiée directement avec Chrome (voir le rapport).
