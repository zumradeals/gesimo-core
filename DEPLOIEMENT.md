# Mettre GESIMO Séjours en ligne sur votre VPS

Site visé : **https://gesimo.dgafrique.com**

> ⚠️ Ces scripts n'ont pas pu être essayés sur un vrai serveur : leur syntaxe est vérifiée, mais le premier lancement
> est à faire avec attention. Faites d'abord une **capture (snapshot) du VPS** chez votre hébergeur : en cas de souci, vous revenez en arrière en 1 minute.

## Ce qu'il vous faut

| Quoi | Détail |
|---|---|
| Un VPS | **Ubuntu 24.04**, 2 Go de mémoire minimum, vide (rien d'autre dessus) |
| L'accès au serveur | Un accès `root` en SSH (adresse IP + mot de passe ou clé) |
| Le nom de domaine | Chez le gestionnaire de `dgafrique.com`, créer un enregistrement **A** : nom `gesimo`, valeur = **l'adresse IP du VPS** |
| Un e-mail | Pour le certificat HTTPS gratuit (Let's Encrypt) |

**Vérifier le domaine avant de commencer** (sur votre ordinateur) : `ping gesimo.dgafrique.com` doit répondre avec l'adresse IP du VPS.
Si ce n'est pas le cas, attendez (jusqu'à quelques heures) : sans cela, le HTTPS ne peut pas être créé.

## 1. Installer (une seule fois, environ 10 minutes)

Connectez-vous au serveur, puis copiez-collez ces lignes (en remplaçant `votre@email.com`) :

```bash
ssh root@ADRESSE_IP_DU_VPS

apt-get update && apt-get install -y git
git clone https://github.com/zumradeals/gesimo-core.git /root/gesimo-install
DOMAINE=gesimo.dgafrique.com EMAIL_CERTIFICAT=votre@email.com bash /root/gesimo-install/deploy/installer.sh
```

Le script installe tout (PHP, base de données PostgreSQL, serveur Nginx, HTTPS, tâche planifiée, traitement en arrière-plan,
sauvegarde quotidienne) et affiche « **Installation terminée** » à la fin. On peut le relancer sans danger.

> **Dépôt privé ?** Si GitHub demande un mot de passe pour `git clone`, le dépôt est privé. Créez sur GitHub une « clé de déploiement »
> (Dépôt → Settings → Deploy keys, lecture seule), puis utilisez l'adresse `git@github.com:zumradeals/gesimo-core.git`
> et ajoutez `DEPOT=git@github.com:zumradeals/gesimo-core.git` devant la dernière commande.

## 2. ⚠️ Sauvegarder la clé maître (très important)

Le fichier **`/root/gesimo-secrets.txt`** contient la **clé maître** (`CLE_MAITRE_APP_KEY=...`).
Elle protège tous les secrets enregistrés dans le site (clés de paiement, mot de passe e-mail…).

```bash
cat /root/gesimo-secrets.txt
```

Copiez ce contenu dans un endroit sûr **hors du serveur** (gestionnaire de mots de passe, coffre). **Sans cette clé, les secrets enregistrés sont perdus.**

## 3. L'assistant d'installation (créer votre compte et régler l'essentiel)

1. Sur le serveur, générez votre **code d'installation** (affiché une seule fois, notez-le) :
   ```bash
   cd /var/www/gesimo && sudo -u www-data php artisan gesimo:installation
   ```
2. Ouvrez **https://gesimo.dgafrique.com/installation** dans votre navigateur, saisissez le code, puis suivez les 5 étapes :
   votre compte super-administrateur → général (nom, e-mail de contact) → e-mail → paiement GeniusPay (mode test) → fin.
3. Chaque étape peut être passée : tout se complète ensuite dans **Réglages** (onglet en bas de l'écran / en haut sur ordinateur).
4. Une fois « Terminer l'installation » cliqué, l'assistant disparaît définitivement.

Le code empêche un inconnu de créer le compte à votre place avant vous. Aucun réglage ne se saisit plus dans un fichier : tout est dans l'espace **Réglages**.

> Secours en ligne de commande (si le navigateur n'est pas disponible) : `sudo -u www-data php artisan gesimo:creer-super-admin`
> puis `sudo -u www-data php artisan gesimo:parametre <réglage> [valeur] [--secret]`.

## 4. Brancher GeniusPay et les e-mails depuis « Réglages »

⚠️ **Sécurité d'abord** : la clé secrète « sandbox » a été visible sur une capture d'écran. Dans le tableau de bord GeniusPay,
**régénérez-la** avant de la saisir, et ne l'envoyez plus jamais par message.

- **Réglages ▸ Notifications** : serveur d'e-mail (hôte, port, utilisateur, mot de passe masqué, adresse d'expédition), puis **Tester la connexion** (un e-mail de test vous est envoyé : « Réussi » ou « Échec » avec l'explication).
- **Réglages ▸ Général** : nom, logo, e-mail de contact de l'équipe (les messages du formulaire « Contact » y arrivent), textes légaux validés par votre juriste.
- **Réglages ▸ Paiement** : dans GeniusPay (mode test), créez un **webhook** vers `https://gesimo.dgafrique.com/webhooks/geniuspay`
  avec les événements `payment.success`, `payment.failed`, `payment.cancelled`, `payment.refunded` ; copiez le secret (`whsec_…`).
  Puis saisissez clé publique, clé secrète et secret du webhook (ils sont chiffrés et jamais réaffichés), le **taux de commission**
  (à vous de le choisir : tant qu'il n'est pas réglé, le paiement en ligne reste fermé) et les frais GeniusPay estimés.
  Enregistrer demande votre mot de passe. **Tester la connexion** demande à GeniusPay le solde du compte (lecture seule).
- **Réglages ▸ Mise en avant** : les offres (nom, durée, prix) proposées aux gérants.
- **Réglages ▸ Réservation / Sécurité** : durée du blocage, acompte par défaut, délai d'annulation gratuite, durée de session, tentatives de connexion.
- Le passage au mode **Réel** se fait plus tard dans Réglages ▸ Paiement (clés réelles + compte GeniusPay vérifié).
- Les rappels d'arrivée partent chaque jour à 9 h par la tâche planifiée (déjà installée).

### Liste de tests à faire sur le vrai bac à sable
1. Réglages ▸ Tests : « Test e-mail » et « Test paiement » doivent répondre « Réussi ».
2. Réserver un logement, aller au paiement, payer en test → la réservation passe à « Confirmée » (page de confirmation + reçu + e-mail).
3. Payer puis fermer la page avant le retour → la réservation est quand même confirmée (le webhook suffit).
4. Abandonner le paiement → la réservation reste « En attente » puis expire.
5. Annuler une réservation payée → le remboursement est demandé ; vérifier dans GeniusPay et dans Admin ▸ Contrôle.
6. Admin ▸ Contrôle : aucun écart ; Admin ▸ Reversements : verser au gérant et marquer « fait ».
7. Gérant ▸ Mises en avant : acheter une offre, payer en test → l'annonce apparaît en tête de recherche avec « Sponsorisé ».
8. Envoyer un faux webhook (`curl -X POST https://gesimo.dgafrique.com/webhooks/geniuspay -d '{}'`) → réponse 401, rien ne change.

## 5. Vérifier que tout marche

1. Ouvrez **https://gesimo.dgafrique.com** : la page de recherche s'affiche, avec le cadenas HTTPS.
2. Ouvrez **https://gesimo.dgafrique.com/up** : la page affiche « OK » (le site est en bonne santé).
3. Connectez-vous avec votre compte super-administrateur (téléphone + mot de passe).
4. Sur téléphone : le menu est en bas de l'écran. Sur ordinateur : en haut.
5. Les réservations « En attente » sont libérées automatiquement : pour le vérifier côté serveur,
   `cd /var/www/gesimo && sudo -u www-data php artisan schedule:list` doit montrer `gesimo:expirer-reservations` chaque minute.

## 6. Mettre à jour le site (à chaque nouvelle version)

```bash
sudo bash /var/www/gesimo/deploy/mise-a-jour.sh
```

Le script sauvegarde la base, passe le site en « maintenance » quelques secondes, met à jour le code et la base, puis remet le site en ligne.

## 7. Sauvegardes

- **Chaque jour**, la base est sauvegardée dans `/var/backups/gesimo/` (les 14 derniers jours sont conservés).
- ⚠️ Ces copies sont **sur le même serveur** : si le VPS disparaît, elles disparaissent aussi. Copiez-les régulièrement ailleurs
  (par exemple avec les sauvegardes/snapshots de votre hébergeur, ou `scp` vers votre ordinateur).
- Les photos sont dans `/var/www/gesimo/storage/app/public` et les pièces d'identité dans `/var/www/gesimo/storage/app/private` : à sauvegarder aussi.
- **Restaurer** une sauvegarde (le site doit être arrêté) :
  ```bash
  systemctl stop gesimo-queue
  sudo -u postgres dropdb gesimo && sudo -u postgres createdb -O gesimo gesimo
  sudo -u postgres pg_restore -d gesimo /var/backups/gesimo/gesimo-AAAA-MM-JJ.dump
  systemctl start gesimo-queue
  ```

## En cas de problème

| Symptôme | Que faire |
|---|---|
| Le HTTPS n'a pas été créé | Vérifiez `ping gesimo.dgafrique.com`, puis relancez la commande d'installation |
| Page blanche ou « 500 » | `tail -50 /var/www/gesimo/storage/logs/laravel.log` |
| Les e-mails ne partent pas | `journalctl -u gesimo-queue -n 50` ; vérifiez les réglages `email.*` (étape 4) |
| Le site est en « maintenance » | `cd /var/www/gesimo && php artisan up` |
| Les photos ne s'affichent pas | `cd /var/www/gesimo && php artisan storage:link` |

## Ce que le script règle pour vous (sécurité)

- Le site fonctionne **uniquement en HTTPS** (redirection automatique, cookies sécurisés).
- Le fichier `.env` ne contient que l'adresse de la base et la clé maître ; il n'est lisible que par le serveur.
- La base PostgreSQL n'écoute que sur le serveur lui-même (jamais sur Internet).
- Pare-feu : seuls SSH (22), HTTP (80) et HTTPS (443) sont ouverts.
- Les pièces d'identité des gérants ne sont jamais accessibles par une adresse publique.
