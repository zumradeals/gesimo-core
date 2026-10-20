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

## 3. Créer votre compte super-administrateur

```bash
cd /var/www/gesimo && sudo -u www-data php artisan gesimo:creer-super-admin
```

Le terminal vous demande votre nom, téléphone (10 chiffres), e-mail, puis un mot de passe (il ne s'affiche pas à l'écran).

## 4. Régler l'envoi d'e-mails

Tant que ce réglage n'est pas fait, **aucun e-mail n'est envoyé** (liens de connexion, invitations du personnel) : ils sont seulement écrits dans un journal.
La connexion par téléphone + mot de passe fonctionne sans e-mail. L'écran Paramètres arrivera dans un prochain lot ; en attendant :

```bash
cd /var/www/gesimo
sudo -u www-data php artisan gesimo:parametre email.hote smtp.votre-fournisseur.com
sudo -u www-data php artisan gesimo:parametre email.port 587
sudo -u www-data php artisan gesimo:parametre email.utilisateur votre-identifiant
sudo -u www-data php artisan gesimo:parametre email.mot_de_passe --secret     # le mot de passe est demandé, masqué
sudo -u www-data php artisan gesimo:parametre email.expediteur_adresse ne-pas-repondre@dgafrique.com
sudo -u www-data php artisan gesimo:parametre email.expediteur_nom "GESIMO Séjours"
```

(`email.chiffrement` vaut `tls` par défaut ; mettre `ssl` pour le port 465.)

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
