# GeniusPay — ce qui est confirmé et ce qui ne l'est pas (20 octobre 2026)

**Source unique :** le code public de l'outil Laravel « geniuspay-laravel » (dépôt GitHub `Genius-Groupsgit/geniuspay-laravel`, version 1.0.0 du 21 mai 2026,
listé sur Packagist). La **documentation officielle** (`pay.genius.ci/doc`) n'a **pas pu être ouverte** depuis l'environnement de travail (adresse introuvable) :
**rien ci-dessous n'est confirmé par la documentation officielle.**

## Lu dans le code de l'outil (probable, à confirmer)
| Sujet | Ce qui est lu |
|---|---|
| Moyens de paiement | Wave, Orange Money, MTN Money, carte bancaire |
| Authentification | en-têtes `X-API-Key` et `X-API-Secret` |
| Créer un paiement | `POST /payments` avec `amount` (entier), `description`, `success_url`, `error_url`, `customer`, `metadata` ; la réponse donne une adresse de paiement (`paymentUrl`) vers laquelle rediriger le client |
| Vérifier un paiement | `GET /payments/{reference}` (on peut donc interroger GeniusPay depuis le serveur) |
| Statuts | `completed`, `pending`, `processing`, `failed`, `cancelled`, `expired` |
| Webhook | `POST` JSON `{ "event": ..., "data": {...} }` ; événements `payment.completed`, `payment.failed`, `payment.cancelled`, `payment.pending`, `refund.completed` ; `data` contient `reference`, `amount`, `currency` (XOF), `fees`, `net_amount`, `payment_method`, `metadata` |
| Signature du webhook | en-tête `X-GeniusPay-Signature` = HMAC-SHA256 (hexadécimal) du **corps brut** avec le « secret webhook » |
| Sandbox | mode test sans vraie transaction ; cartes de test `4084 0841 1111 1111` (succès) et `4084 0841 2222 2222` (échec) |
| Versement (cashout) | `POST /cashouts` existe (`amount`, `phone`, `method`) — **non utilisé** (pas de versement automatique sans accord écrit) |

## Non confirmé ou problématique
1. **Adresse de l'API** : l'outil contient deux adresses différentes (`https://geniuspay.ci/api/v1/merchant` et `https://pay.geniuspay.io/api/v1/merchant`) ; une seule sert pour test et réel (le mode test dépend probablement des clés).
2. **Forme exacte de la réponse** de création (nom du champ de l'adresse de paiement, du champ `reference`).
3. **Remboursement** : aucune fonction de remboursement par programme trouvée (seulement l'événement `refund.completed`) → remboursements **manuels**.
4. **Pas d'horodatage ni d'identifiant d'événement** dans le webhook : un message valide pourrait être rejoué, et un même événement peut arriver plusieurs fois → il faut une protection côté GESIMO (déjà prévue : références uniques, effets uniques).
5. L'outil GeniusPay **accepte les webhooks sans vérification si le secret est vide** : GESIMO refusera toujours un webhook sans secret configuré.
6. Durée de validité du lien de paiement, nouvelles tentatives d'envoi des webhooks, adresses IP d'envoi, frais exacts, plafonds, gestion du « paiement après expiration » : **inconnus**.
7. **Partage automatique du paiement / versement par API** (point ouvert n°4 du cahier) : non confirmé → reversement manuel.
8. Remboursement partiel, litiges/rétrofacturation : inconnus.
9. Clés « sandbox » : il en faut pour tester pour de vrai ; je n'en ai pas, et mon environnement ne peut pas joindre GeniusPay.

## Questions à poser à GeniusPay (pay@genius.ci)
1. Quelle est l'adresse exacte de l'API, en test et en réel ? Comment obtenir des clés de test ?
2. Format exact de la réponse de création d'un paiement.
3. Signature des webhooks : confirmer `X-GeniusPay-Signature`, HMAC-SHA256 du corps brut ; y a-t-il un horodatage ? un identifiant d'événement ?
4. Les webhooks sont-ils renvoyés en cas d'échec ? Combien de fois ? D'où viennent-ils (adresses IP) ?
5. Existe-t-il un remboursement par programme (total/partiel) ? Délais ?
6. Durée de vie d'un lien de paiement ? Que se passe-t-il si le client paie après ?
7. Frais exacts par moyen de paiement ; le partage automatique entre GESIMO et le gérant est-il possible ?
8. Le versement automatique (cashout) est-il autorisé pour des fonds de tiers ? (accord écrit demandé)
