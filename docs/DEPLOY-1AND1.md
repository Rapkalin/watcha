# Déploiement sur un hébergement 1&1 (IONOS)

Ce guide couvre un déploiement **sans Docker ni Kubernetes**, sur un hébergement mutualisé ou un
VPS 1&1, avec **PHP 8.3** et **MySQL 8**.

> ⚠️ Sur de l'hébergement **mutualisé**, vous n'avez pas toujours d'accès SSH ni de cron riche.
> Vérifiez dans le panneau IONOS : version de PHP (réglez-la sur **8.3**), accès SSH, planificateur
> de tâches (cron), et la possibilité de définir le **dossier racine (docroot)** du domaine.

## 1. Préparer un artefact de production

En local (ou via le job `build:prod` de la CI) :

```bash
# Variables d'environnement de prod compilées dans .env.local.php
composer dump-env prod

# Dépendances optimisées sans dev
composer install --no-dev --optimize-autoloader

# Compilation des assets (SCSS -> CSS, puis empreintes AssetMapper)
php bin/console sass:build
php bin/console asset-map:compile

php bin/console cache:clear --env=prod
```

Configurez le secret et la base dans `.env.local` **avant** `dump-env` (ne committez jamais ce
fichier) :

```dotenv
APP_ENV=prod
APP_SECRET=<chaîne aléatoire de 32+ caractères>
DATABASE_URL="mysql://UTILISATEUR:MOTDEPASSE@HOTE_MYSQL_IONOS:3306/NOM_BASE?serverVersion=8.0&charset=utf8mb4"
WPSCAN_API_TOKEN=        # optionnel
```

## 2. Téléverser les fichiers

Transférez l'ensemble du projet (SFTP, `rsync` ou artefact CI) dans un dossier **hors web**, par
exemple `~/watcha`. **Exception** : le contenu servi est le dossier `public/`.

```
~/watcha/            <- code applicatif (non accessible directement par le web)
  ├── bin/ config/ src/ templates/ var/ vendor/ migrations/
  └── public/           <- DOCROOT du domaine
```

## 3. Pointer le domaine sur `public/`

Dans le panneau IONOS, réglez le **docroot** du domaine (ou sous-domaine) sur le dossier
`public/`. Le fichier `public/.htaccess` (fourni par `symfony/apache-pack`) gère la réécriture vers
`index.php` ; assurez-vous que `mod_rewrite` est actif (c'est le cas par défaut chez IONOS).

> Si vous ne pouvez pas changer le docroot (certaines offres mutualisées), placez le contenu de
> `public/` à la racine web et ajustez `index.php` pour pointer vers `../` — mais privilégiez
> toujours un docroot dédié pour ne pas exposer le code.

> 💡 Si vous déployez via **GitHub Actions** (`infra/deploy/release.sh`), la disposition serveur
> diffère : le docroot pointe sur `~/<env>-watcha/website/public` — voir le [§8](#8-déploiement-via-github-actions-infradeployreleasesh).

## 4. Base de données

Créez la base MySQL 8 depuis le panneau IONOS, renseignez `DATABASE_URL`, puis appliquez le schéma :

```bash
php bin/console doctrine:migrations:migrate --no-interaction --env=prod
```

Sans accès SSH, exécutez la migration en local **contre la base distante** (si elle est joignable),
ou importez le SQL généré par :

```bash
php bin/console doctrine:migrations:migrate --write-sql=dump.sql --env=prod
```

## 5. Créer le premier administrateur

```bash
php bin/console app:user:create --email=admin@votre-domaine.fr --password='…' --role=admin --env=prod
```

## 6. Cron (synchronisation CVE + scans)

Dans le planificateur de tâches IONOS, ajoutez deux tâches (adaptez le chemin de `php` et du
projet). Exemple de crontab :

```cron
# Import des advisories à 03:00, puis scan des sites à 03:30
0 3 * * *  /usr/bin/php8.3 /homepages/xx/dxxxxx/htdocs/watcha/bin/console app:cve:sync   --env=prod >> ~/watcha/var/log/cron.log 2>&1
30 3 * * * /usr/bin/php8.3 /homepages/xx/dxxxxx/htdocs/watcha/bin/console app:sites:scan --env=prod >> ~/watcha/var/log/cron.log 2>&1
```

> Si le cron mutualisé n'autorise qu'une URL HTTP (pas de binaire), exposez une route protégée par
> un secret qui déclenche `app:cve:sync`/`app:sites:scan`, et appelez-la via le planificateur. À
> défaut, lancez ces commandes manuellement ou depuis un poste de confiance.

## 7. Permissions

Le dossier `var/` (cache, logs, sass) doit être **inscriptible** par le serveur web :

```bash
chmod -R 775 var
```

## 8. Déploiement via GitHub Actions (`infra/deploy/release.sh`)

Le déploiement automatisé reprend le modèle « in-place + snapshot » d'anim (script versionné
`infra/deploy/release.sh`, lisible et rejouable à la main). Disposition serveur, **un dossier par
environnement** :

```
~/<env>-watcha/
├── website/        # app live — le docroot 1&1 pointe sur website/public
├── shared/         # persistant, jamais écrasé
│   ├── .env        # secrets prod (symlinké vers website/.env)
│   ├── var/log/    # logs conservés entre déploiements
│   └── .htpasswd   # OPTIONNEL — sa présence active un Basic Auth (utile en preprod)
└── old_website/    # déploiement précédent (snapshot hardlink) — pour rollback
```

À chaque déploiement, `release.sh` : snapshot `website → old_website` (`cp -al`, hardlinks → quasi
instantané), rsync de la nouvelle version dans `website/`, re-symlink de `shared/.env` et
`shared/var/log`, puis `cache:clear` + `cache:warmup` + `migrations:migrate --all-or-nothing`.

**Pré-requis (une fois, à la main)** : créer `~/<env>-watcha/shared/.env` avec au moins
`APP_ENV=prod`, `APP_SECRET=…`, `DATABASE_URL=…` (et `WPSCAN_API_TOKEN=` si besoin). Les commandes
console n'utilisent pas `--env` : l'environnement vient de ce `shared/.env`.

> Le docroot 1&1 doit pointer sur **`~/<env>-watcha/website/public`** (et non plus `public/`
> directement). Le `.env`/`shared` ne sont jamais touchés par le rsync (`--delete` protège les
> fichiers exclus).

### Rollback (sur le serveur, dans `~/<env>-watcha`)

```bash
rm -rf website && cp -al old_website website
cd website && /usr/bin/php8.3-cli bin/console cache:clear
```

> `old_website` ne conserve que **la version précédente** (le code uniquement). Les migrations DB
> déjà appliquées ne sont pas annulées — gardez des migrations additives.

### Déploiement manuel (sans CI)

Vous pouvez exécuter le même script depuis un poste disposant de l'accès SSH :

```bash
DEPLOY_HOST=… DEPLOY_USER=… DEPLOY_DIR=prod-watcha DEPLOY_PHP_BIN=/usr/bin/php8.3-cli \
  bash infra/deploy/release.sh
```

## Checklist sécurité

- [ ] `APP_ENV=prod`, `APP_DEBUG=0`, profiler désactivé (déjà le cas en prod).
- [ ] `APP_SECRET` unique et secret (jamais committé).
- [ ] `.env.local` / `.env.local.php` exclus du dépôt (déjà dans `.gitignore`).
- [ ] HTTPS forcé sur le domaine (réglage IONOS).
- [ ] Accès MySQL restreint à l'application.
```
