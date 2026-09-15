# Oracle — migration GitHub-as-DB → PHP/MySQL

## Structure

```
/
├── index.html              (ex oracle.html, endpoints mis à jour)
├── auth-api.js             → à placer dans /js/auth-api.js
├── manifest.json, sw.js, images/  (inchangés, pas dans ce zip — reprends tes fichiers actuels)
├── config/
│   ├── db.php               PDO — À ÉDITER (host/dbname/user/pass)
│   ├── helpers.php          JSON, auth HMAC, normalisation (identique JS)
│   └── .htaccess            deny all
├── api/
│   ├── auth/{register,login,me,users,user-history,user-logs,errors}.php
│   ├── sync/{search,log,immatriculations}.php
│   ├── admin/import-immatriculations.php
│   ├── search-registry.php  nouveau — remplace le système de fragments
│   └── proxy.php            proxy ANaTT (ajuster $allowedHost)
├── sql/schema.sql
└── migration/import-from-json.php
```

## Déploiement

1. **BDD** : cPanel → MySQL Databases → crée `<cpanel_user>_oracle_db` + user dédié + tous privilèges. Puis `sql/schema.sql` via phpMyAdmin (import direct, pas besoin d'adapter le nom de la base — retire juste la ligne `CREATE DATABASE` si cPanel te force un nom précis déjà créé).
2. **`config/db.php`** : remplace les 2 `CHANGE_MOI` (user/pass cPanel réels). Host reste `localhost` en général sur cPanel.
3. **`config/helpers.php`** : remplace `SESSION_SECRET` (chaîne aléatoire longue, `bin2hex(random_bytes(32))` en local pour en générer une).
4. **`api/proxy.php`** : `$allowedHost = 'anatt.bj'` — vérifie le vrai domaine ANaTT utilisé actuellement côté client et ajuste si besoin.
5. Upload de toute l'arborescence à la racine du domaine (`public_html/` ou sous-domaine dédié). `index.html` inchangé pour le reste (manifest, sw.js, images/) — recopie-les depuis ta version actuelle.
6. **`js/auth-api.js`** ← le fichier `auth-api.js` fourni ici, à ce chemin précis (l'URL est en dur dans `index.html`).

## Migration des données

```bash
php migration/import-from-json.php data/immatriculations/fragment-*.json
```
ou directement l'ancien fichier unique si tu n'as pas encore basculé sur les fragments :
```bash
php migration/import-from-json.php data/Immatriculations.json
```
Idempotent en usage normal mais **pas de déduplication à l'import** (contrairement à `admin/import-immatriculations.php`) — ne le lance qu'une fois sur une table vide. Transaction unique : soit tout passe, soit rollback complet (déjà testé sur 101 356 lignes réelles, 12s, RAS).

Si SSH n'est pas dispo sur ton hébergement, adapte-le en 5 min en script web (boucle `foreach ($_FILES...)`, à supprimer après usage — ne le laisse jamais accessible en prod tel quel vu qu'il n'a aucune auth).

## Points d'attention

- **Colonnes `VARCHAR(191)`** sur `immatriculation`/`chassis`/`departement`/`nom_prenom` : volontairement large — j'ai trouvé des messages d'erreur ANaTT ("Est prié de se rapprocher de l'annexe de...") mal atterris dans le champ `immatriculation` sur tes vraies données. `utf8mb4` + clé indexée > 191 char nécessiterait `innodb_large_prefix` selon ta version MySQL — j'ai volontairement évité d'y toucher.
- **Session** : HMAC-SHA256 maison (payload base64url + sig), calqué sur l'ancien format Vercel pour cohérence. Rien d'exotique, mais si tu préfères repasser sur de vraies sessions PHP natives (`$_SESSION`) plutôt qu'un Bearer token stateless, c'est un refactor isolé à `config/helpers.php` + les `Authorization: Bearer` côté `auth-api.js`.
- **`sync/immatriculations.php` et `admin/import-immatriculations.php`** dupliquent la même logique de fusion (vide→complète, doublon exact→skip, propriétaire différent→nouvelle ligne). Volontaire pour l'instant (repris tel quel de la logique déjà en prod côté Vercel) — factorisable dans `helpers.php` si tu veux nettoyer plus tard, aucune urgence fonctionnelle.
- **Pas de sharding, plus besoin** : `search-registry.php` fait une requête indexée directe. Testé à 101 356 lignes réelles : ~77ms en dev server PHP (donc mieux en prod Apache+opcache).
- **CORS ouvert (`Access-Control-Allow-Origin: *`)** dans `handleCors()` — resserre à ton domaine exact si l'app et l'API sont bien sur le même host (ce qui est le cas dans ce déploiement "tout cPanel").

## Non inclus dans ce zip (déjà chez toi, inchangés)

`manifest.json`, `sw.js`, `images/`, `vercel.json` (obsolète, à supprimer). Pense à changer les URLs absolues codées en dur pointant vers l'ancien domaine Vercel si tu en as laissé quelque part (ex. og:image dans `index.html` — vérifie, ça devrait déjà être en chemin relatif `/images/...`).
