# Journal de classe

Site personnel de suivi quotidien pour enseignant·e : écoles › classes › élèves, horaire,
présences par période, appréciations, journal de classe (calendrier), évaluations avec photo,
conseils de classe, archivage par année scolaire et congés de la Fédération Wallonie-Bruxelles.

Technologies : **PHP 8.1+ et MySQL**, sans framework ni bibliothèque externe — prévu pour
un **hébergement mutualisé OVH**.

---

## 1. Installation chez OVH (≈ 15 minutes)

### a) Créer la base de données
1. Espace client OVH › *Web Cloud* › *Hébergements* › votre hébergement › onglet **Bases de données**.
2. **Créer une base de données** (MySQL). Notez : serveur (`xxxxx.mysql.db`), nom de la base, utilisateur, mot de passe.

### b) Envoyer les fichiers
1. Avec FileZilla (identifiants FTP dans l'onglet *FTP-SSH*), envoyez **tout le contenu** de ce dossier dans `www/`
   (y compris les fichiers cachés `.htaccess` et `.ovhconfig`).
2. **Recommandé** : onglet *Multisite* › modifier votre domaine › **Dossier racine** = `www/public`.
   Ainsi, seuls les fichiers du dossier `public/` sont accessibles depuis Internet.
   (Si vous ne le faites pas, le fichier `.htaccess` à la racine redirige quand même tout vers `public/`
   et bloque les dossiers privés.)
3. Activez le **certificat SSL** gratuit (onglet *Multisite*, case SSL) : le site impose le HTTPS.

### c) Configurer
1. Dans `config/`, copiez `config.example.php` en **`config.php`**.
2. Remplissez :
   - `app_url` : l'adresse du site ;
   - `db` : les informations de la base (étape a) ;
   - `secret_key` : une longue phrase aléatoire (par ex. générée par un gestionnaire de mots de passe, 64 caractères) ;
   - `install_token` : un code temporaire de votre choix, demandé à l'étape suivante ;
   - `mail.from` : une adresse **de votre domaine** (ex. `no-reply@mondomaine.be`). OVH refuse les autres.

### d) Installer
1. Ouvrez `https://votre-domaine/install.php`.
2. Entrez le code `install_token`, votre nom, votre email et un mot de passe (12 caractères min.).
3. **Supprimez ensuite le fichier `public/install.php`** de l'hébergement (il est de toute façon bloqué une fois l'installation faite).
4. Connectez-vous : un **code à 6 chiffres** arrive par email. Pensez à vérifier les spams la première fois.

### e) Premiers pas dans le site
1. *Écoles & élèves* : ajoutez vos écoles, puis vos classes (couleur au choix).
2. Dans une classe, **collez la liste des élèves** (un par ligne, « NOM Prénom » ou « Nom, Prénom »).
3. *Horaire* : pour chaque cours, choisissez la classe, le jour, la **première période** et la **durée** (1 à 4 périodes).
4. Chaque jour, *Aujourd'hui* affiche vos cours : cliquez pour prendre les présences.

---

## 2. Sécurité (données sensibles)

| Protection | Où dans le code |
|---|---|
| Double authentification : mot de passe + code email à 6 chiffres (10 min, 5 essais, haché) | `app/lib/auth.php` |
| Blocage 15 min après 5 échecs (par adresse IP et par email) | `is_locked_out()` dans `auth.php` |
| Mots de passe hachés (bcrypt via `password_hash`) | `auth.php`, `install.php` |
| Session : cookie HttpOnly + Secure + SameSite, déconnexion après 30 min d'inactivité et 12 h max, liée au navigateur | `app/lib/security.php` |
| Jeton anti-CSRF sur **tous** les formulaires | `csrf_field()` / `csrf_check()` |
| Requêtes SQL préparées uniquement (pas d'injection SQL) | `app/lib/db.php` |
| Tout texte affiché est échappé (pas de XSS) | `e()` dans `helpers.php` |
| En-têtes de sécurité stricts (CSP, anti-iframe, HSTS, pas de cache) | `send_security_headers()` |
| Photos stockées **hors du dossier public**, vérifiées, ré-encodées (suppression GPS), servies uniquement après connexion | `app/lib/uploads.php`, `public/photo.php` |
| Dossiers `app`, `config`, `database`, `storage` interdits depuis le web | fichiers `.htaccess` |
| Journal des connexions (réussies et échouées) visible dans *Mon compte* | table `audit_log` |
| Aucun script, police ou service externe chargé (rien ne fuit vers Google & co) | — |
| Pages non indexées par les moteurs de recherche | balise `robots noindex` |

**Conseils** : utilisez un mot de passe unique ; protégez bien la boîte mail qui reçoit les codes
(elle devient la « clé » du site) ; OVH sauvegarde les bases automatiquement, mais faites aussi un
export régulier via phpMyAdmin (onglet *Bases de données* › *Sauvegarder*).

---

## 3. Comprendre et modifier le code

```
journal-de-classe/
├── config/            configuration (config.php = vos mots de passe, à ne jamais partager)
├── app/
│   ├── bootstrap.php  chargé en premier par chaque page
│   ├── lib/
│   │   ├── school.php        ⭐ PÉRIODES (horaires 8h25…), statuts A/P/AT/R, couleurs d'appréciation
│   │   ├── fwb_calendar.php  ⭐ calendrier officiel FWB (ajoutez les années futures ici)
│   │   ├── auth.php          connexion + code email
│   │   ├── security.php      session, CSRF, en-têtes
│   │   ├── calendar.php      congés, horaire → cours du jour
│   │   ├── stats.php         calculs des statistiques et moyennes
│   │   ├── uploads.php       photos
│   │   ├── years.php         années scolaires / archivage
│   │   ├── view.php          menu, icônes, petits composants
│   │   └── helpers.php, db.php, mailer.php
│   └── views/         en-tête et pied de page HTML
├── database/schema.sql  structure de la base (commentée)
├── public/            ⭐ les pages du site (1 fichier = 1 page)
│   ├── assets/app.css   ⭐ toutes les couleurs en haut du fichier (:root)
│   └── assets/app.js    petites interactions (aucune bibliothèque)
└── storage/           photos, sessions, journaux (privé)
```

Chaque page de `public/` suit le même schéma, de haut en bas :
1. `require bootstrap.php` puis `require_login()` ;
2. traitement du formulaire si `is_post()` (puis `redirect()`) ;
3. lecture des données avec `fetch_all()` / `fetch_one()` ;
4. affichage HTML entre `page_start()` et `page_end()`.

**Exemples de modifications courantes**
- Changer un horaire de période : `app/lib/school.php`, constante `PERIODS`.
- Changer les couleurs du site : `public/assets/app.css`, bloc `:root` tout en haut.
- Ajouter l'année 2028-2029 : copiez un bloc dans `app/lib/fwb_calendar.php`.
- Changer le libellé d'une appréciation : `APPRECIATIONS` dans `school.php`.
- Ajouter un champ : ajoutez la colonne en base (phpMyAdmin), puis le champ dans la page concernée.

### Tester sur votre ordinateur (facultatif)
Avec PHP et MySQL installés (par ex. XAMPP) : créez `config/config.php`, mettez `'mail' => ['debug' => true]`
(le code de connexion est alors écrit dans `storage/logs/mail.log`), puis lancez
`php -S localhost:8000 -t public`.

---

## 4. Règles de calcul

- **Présence** : P (présent) et AT (arrivée tardive) = présent ; A (absent) et R (renvoyé) = absent. Calcul par période.
- **Tranche** : un cours d'une à quatre périodes d'affilée avec une classe. Les appréciations (vert, orange, rouge, noir) se donnent par tranche ; les statistiques mensuelles comptent les tranches.
- **Moyenne** : total des points obtenus ÷ total des points possibles, ramené sur 20 et sur 100. Les absences ne comptent pas.
- **Élève désactivé** : grisé, n'apparaît plus dans les présences, ses données restent.
- **Cours annulé** : exclu des statistiques et des présences manquantes.
- **Année archivée** : consultable, plus modifiable (désarchivage possible).
