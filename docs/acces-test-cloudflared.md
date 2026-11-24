# Faire tester SafeGP depuis mon PC (cloudflared)

Ce document explique comment donner un lien vers SafeGP à des testeurs
**sans mettre le site en ligne**, et ce qui protège les fichiers sensibles.
Il n'est pas accessible depuis le web (le dossier `docs/` est bloqué par `.htaccess`).

---

## 1. Le principe : un port réservé à SafeGP

XAMPP sert tout `C:\xampp\htdocs` sur le port **80** : SafeGP, mais aussi
phpMyAdmin, le tableau de bord XAMPP, les autres projets et `SafeGP.zip`.

> ⚠️ **Ne jamais lancer le tunnel sur le port 80.**
> cloudflared se connecte depuis le PC lui-même : Apache croit que la visite
> est « locale » et ouvre phpMyAdmin (base complète, root sans mot de passe)
> à toute personne qui a le lien.

On utilise donc le port **8080**, qui ne montre **que le dossier SafeGP** :

| Adresse | Ce qu'on voit |
|---|---|
| `http://localhost/SafeGP` | comme avant (pour moi, en local) |
| `http://localhost:8080` | SafeGP seul, à la racine (comme en ligne) |
| `http://localhost:8080/phpmyadmin` | refusé (403) |

### Où est ce réglage ?

- Fichier : `C:\xampp\apache\conf\extra\httpd-vhosts.conf`, en bas, bloc
  « SafeGP seul, sur le port 8080 » (`Listen 8080` + `<VirtualHost *:8080>`).
- Il est en dehors du projet : il n'est **pas** dans git. Si XAMPP est
  réinstallé, il faut le remettre (copie ci-dessous).
- Après toute modification : **XAMPP → Apache → Stop puis Start**.

```apache
Listen 8080
<VirtualHost *:8080>
    ServerName localhost
    DocumentRoot "C:/xampp/htdocs/SafeGP"
    <Directory "C:/xampp/htdocs/SafeGP">
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    <LocationMatch "^/(phpmyadmin|phpMyAdmin|xampp|dashboard|webalizer|security|licenses|img|server-status|server-info)(/|$)">
        Require all denied
    </LocationMatch>
</VirtualHost>
```

---

## 2. Lancer et arrêter le tunnel

1. Démarrer **Apache** et **MySQL** dans le panneau XAMPP.
2. Vérifier `http://localhost:8080` dans le navigateur.
3. Dans PowerShell :

   ```powershell
   cloudflared tunnel --url http://localhost:8080
   ```

4. cloudflared affiche un lien du type `https://mots-au-hasard.trycloudflare.com` :
   c'est le lien à partager.
5. Pour couper l'accès : **Ctrl + C** dans la fenêtre PowerShell (ou la fermer).

### Lien temporaire, pas fixe

- Ce « tunnel rapide » ne demande aucun compte, mais le lien **change à chaque
  lancement** et ne marche que tant que la fenêtre est ouverte et le PC allumé.
- Si on relance cloudflared, il faut renvoyer le nouveau lien aux testeurs.
- Un lien fixe demande un compte Cloudflare et un nom de domaine (tunnel nommé).
  Inutile pour des tests : le vrai lien sera celui de l'hébergeur.

---

## 3. Ce que vivent les testeurs

- Le site ne se considère pas « en local » à travers le tunnel :
  les erreurs techniques ne sont pas affichées aux visiteurs.
- Sans SMTP configuré dans `config.php`, **aucun e-mail n'est envoyé** :
  l'inscription marche sans confirmation d'e-mail, la connexion aussi.
  « Mot de passe oublié » ne peut pas fonctionner pendant les tests.
- Si le SMTP est configuré, les liens des e-mails pointent vers `SITE_URL`
  (l'adresse en ligne future), pas vers le lien du tunnel.
- Leurs comptes, annonces, messages, photos et pièces d'identité arrivent dans
  **ma base locale** et dans `uploads/`, `preuves/`, `verifications/`.
  Penser à faire le ménage (ou garder ces comptes de test) avant la mise en ligne.

---

## 4. Fichiers sensibles : où ils sont et pourquoi ils sont bloqués

Les règles sont dans **`.htaccess`** à la racine du projet (elles s'appliquent
aussi chez l'hébergeur).

| Fichier / dossier | Ce qu'il contient | Protection |
|---|---|---|
| `config.php` | mots de passe base de données, SMTP, coordonnées de paiement | accès direct refusé (403), jamais dans git |
| `config.example.php` | modèle de configuration | accès direct refusé (403) |
| `.git/` | tout l'historique du code | 404 (tout ce qui commence par un point) |
| `.gitignore`, `.htaccess` | réglages internes | 404 / 403 |
| `schema.sql`, `migrations/` | structure de la base | 403 / 404 |
| `logs/` | erreurs PHP, e-mails enregistrés en local | 403 (`logs/.htaccess`) |
| `preuves/` | preuves de paiement, photos des colis | 403 (`preuves/.htaccess`), servi seulement par `fichier_prive.php` |
| `verifications/` | pièces d'identité | 403 (`verifications/.htaccess`), servi seulement par `piece_identite.php` |
| `docs/`, `pubs/`, `screenshots/` | documentation, affiches et vidéos, captures | 404 |
| `*.md`, `*.txt`, `*.log`, `*.zip`, `*.bak`, `*.sauvegarde`… | notes, archives, copies | 403 (sauf `robots.txt`) |

En dehors du projet :

| Élément | Risque | À faire |
|---|---|---|
| `C:\xampp\htdocs\SafeGP.zip` | vieille archive avec un ancien `config.php` et `.git` | la supprimer ou la sortir de `htdocs` |
| phpMyAdmin (`/phpmyadmin`) | base complète | jamais de tunnel sur le port 80 |

### Vérification rapide (Apache démarré)

Ces adresses doivent répondre **403 ou 404** :

```
http://localhost:8080/.git/config
http://localhost:8080/.gitignore
http://localhost:8080/config.php
http://localhost:8080/schema.sql
http://localhost:8080/logs/erreurs.log
http://localhost:8080/phpmyadmin/
http://localhost:8080/docs/acces-test-cloudflared.md
```

Et celles-ci doivent s'afficher normalement : `/`, `/search_trajet.php`,
`/suivi.php`, `/connexion.php`, `/robots.txt`.
