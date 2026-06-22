# Watcha 🛡️

Tableau de bord de veille de vulnérabilités pour vos sites **Symfony, Laravel, Drupal et
WordPress**. Vous enregistrez l'URL d'un site, l'application détecte la technologie et la version
utilisées, puis lève une alerte dès qu'une **CVE** correspond à cette version ou qu'une **nouvelle
version / un correctif** est disponible.

Application **monolithique Symfony 7.4 / PHP 8.3 / MySQL 8**, derrière authentification, avec
trois niveaux d'utilisateurs.

---

## Sommaire

- [Fonctionnalités](#fonctionnalités)
- [Stack technique](#stack-technique)
- [Démarrage rapide (Docker)](#démarrage-rapide-docker)
- [Installation sans Docker](#installation-sans-docker)
- [Rôles et workflow d'inscription](#rôles-et-workflow-dinscription)
- [Sources de données CVE](#sources-de-données-cve)
- [Détection de version](#détection-de-version)
- [Commandes console & cron](#commandes-console--cron)
- [Qualité, tests et sécurité](#qualité-tests-et-sécurité)
- [Déploiement sur 1&1](#déploiement-sur-11)
- [Architecture](#architecture)

---

## Fonctionnalités

- 🔐 **Login + inscription** : un compte `basic` créé en self-service doit être **approuvé** par un
  maintainer/admin avant de pouvoir se connecter.
- 👥 **3 rôles** : `basic`, `maintainer`, `admin` (voir [plus bas](#rôles-et-workflow-dinscription)).
- 🌐 **Suivi de sites** : ajout d'une URL → scan automatique → techno + version détectées.
- 🚨 **Alertes** : CVE applicable à la version détectée **et/ou** mise à jour disponible.
- 🔎 **Base de vulnérabilités** consultable et filtrable par technologie.
- 🎨 **UI SCSS** inspirée de [shadcn/ui](https://ui.shadcn.com/) (aucun Bootstrap/Tailwind), mode
  clair/sombre automatique.

## Stack technique

| Composant      | Choix                                            |
|----------------|--------------------------------------------------|
| Langage        | PHP 8.3                                           |
| Framework      | Symfony 7.4 (LTS), monolithique                   |
| ORM / BDD      | Doctrine ORM 3 / MySQL 8                           |
| Front          | Twig + AssetMapper + SCSS (`symfonycasts/sass-bundle`) |
| Auth           | `symfony/security-bundle` (form login, CSRF session) |
| HTTP externe   | `symfony/http-client`                             |
| Versions       | `composer/semver`                                 |
| Conteneurs     | Docker Compose (dev uniquement)                   |
| CI/CD          | GitHub Actions                                    |

## Démarrage rapide (Docker)

```bash
make up            # build + démarre php-fpm, nginx, mysql, mailpit
make migrate       # crée le schéma de base
make fixtures-admin # crée admin@watcha.test / AdminPassw0rd!
make sync          # importe les advisories (réseau requis)
```

- Application : <http://localhost:8085> (changer de port si besoin : `HTTP_PORT=8090 docker compose up -d`)
- Mails de dev (Mailpit) : <http://localhost:8025>

> Les conteneurs servent **uniquement au développement**. La production tourne en PHP « classique »
> (voir [Déploiement sur 1&1](#déploiement-sur-11)).

## Installation sans Docker

Prérequis : PHP 8.3 (`pdo_mysql`, `intl`, `zip`), Composer 2, MySQL 8.

```bash
composer install
cp .env .env.local          # puis renseignez DATABASE_URL et APP_SECRET
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
php bin/console app:user:create --email=vous@example.com --password='…' --role=admin
php bin/console sass:build
symfony serve                # ou: php -S 127.0.0.1:8000 -t public public/index.php
```

## Rôles et workflow d'inscription

| Rôle         | Accès dashboard | Voir la liste des users | Approuver un compte | Modifier rôles / supprimer |
|--------------|:---------------:|:-----------------------:|:-------------------:|:--------------------------:|
| `basic`      | ✅              | ❌                      | ❌                  | ❌                         |
| `maintainer` | ✅              | ✅                      | ✅                  | ❌                         |
| `admin`      | ✅              | ✅                      | ✅                  | ✅                         |

Hiérarchie : `ROLE_ADMIN` ⊃ `ROLE_MAINTAINER` ⊃ `ROLE_USER`.

**Inscription** : `/register` crée un compte `basic` **non approuvé**. La connexion est refusée
(message explicite via `AppUserChecker`) tant qu'un maintainer/admin ne l'a pas approuvé depuis
`/admin/users`.

## Sources de données CVE

L'import est **pluggable** (`AdvisoryProviderInterface`, tag `app.advisory_provider`) :

- **OSV.dev** (`OsvProvider`) — couvre l'écosystème Packagist : `symfony/symfony`,
  `laravel/framework`, `drupal/core`. Aucune clé requise.
- **WPScan** (`WordPressProvider`) — WordPress core. Nécessite un token API gratuit
  (`WPSCAN_API_TOKEN`). Sans token, le provider ne renvoie rien (un avertissement est journalisé) ;
  les alertes « mise à jour disponible » WordPress continuent de fonctionner via le flux de versions
  de wordpress.org.

Ajouter une source = créer une classe implémentant `AdvisoryProviderInterface` ; elle est
automatiquement collectée par `AdvisorySynchronizer`.

## Détection de version

`SiteScanner` récupère la page d'accueil et interroge chaque détecteur
(`TechnologyDetectorInterface`). Le résultat le plus « confiant » l'emporte.

| Techno    | Signaux                                            | Version détectable ? |
|-----------|----------------------------------------------------|----------------------|
| WordPress | `<meta generator>`, `/wp-content/`, `/readme.html` | ✅ souvent            |
| Drupal    | en-tête `X-Generator`, `<meta Generator>`          | ⚠️ version majeure    |
| Laravel   | cookies `laravel_session` / `XSRF-TOKEN`           | ❌ (non exposée)      |
| Symfony   | en-têtes debug, cookie de session                  | ❌ (non exposée)      |

> **Limite connue** : Symfony et Laravel n'exposent pas publiquement leur version. La techno est
> détectée mais la version reste vide ; les alertes CVE ne se déclenchent que lorsqu'une version est
> connue. Les alertes « mise à jour » s'appuient sur la dernière version stable publiée (Packagist).

## Commandes console & cron

```bash
php bin/console app:cve:sync            # importe/maj les advisories (toutes technos)
php bin/console app:cve:sync -t symfony # une seule techno
php bin/console app:sites:scan          # re-scanne tous les sites + recalcule les alertes
php bin/console app:user:create …       # crée un compte approuvé (bootstrap admin)
```

Planifiez `app:cve:sync` puis `app:sites:scan` (ex. quotidiennement) — voir le cron 1&1 ci-dessous.

## Qualité, tests et sécurité

```bash
make quality   # php-cs-fixer + phpstan + phpunit
make cs-fix    # corrige le style
make stan      # PHPStan (niveau 6)
make test      # PHPUnit
composer audit # dépendances vulnérables
```

### CI/CD — GitHub Actions

`.github/workflows/ci.yml` exécute le pipeline : **quality** (`composer cs-check`,
`composer phpstan`, lint container/twig/yaml, `composer audit`) → **test** (PHPUnit + service
MySQL 8) → **secret-detection** (gitleaks) → **build** (install prod + assets compilés, artefact) →
**deploy** (SSH + rsync « in-place » via le script versionné `infra/deploy/release.sh`).

- Le job `cve-audit` tourne aussi **chaque jour** (cron `0 6 * * *`) pour échouer dès qu'une nouvelle
  CVE touche les dépendances verrouillées ; build/deploy sont ignorés sur les runs planifiés.
- **Déploiement** (inerte tant que `DEPLOY_HOST` n'est pas défini) — un environnement par cible :
  `integration` (push `develop`), `recette` (tag `X.Y.Z`), `preprod` (push `main`), `production`
  (`main`, **approbation manuelle** via les *required reviewers* de l'environnement GitHub).
  Déploiement « in-place » dans `~/<env>-watcha/website/` (docroot = `website/public`), avec
  un `shared/.env` persistant et un snapshot **`old_website/`** (hardlinks) avant chaque déploiement
  pour le **rollback** (`rm -rf website && cp -al old_website website`). Voir
  [`docs/DEPLOY-1AND1.md`](docs/DEPLOY-1AND1.md) et `infra/deploy/release.sh`.
- À configurer (Settings → Secrets and variables → Actions) :
  - Variables : `DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_PORT` (défaut 22), `DEPLOY_PHP_BIN` (défaut `/usr/bin/php8.3-cli`)
  - Secrets : `SSH_PRIVATE_KEY`, `SSH_KNOWN_HOSTS`
  - Environnements : `integration`, `recette`, `preprod`, `production` (ajouter un reviewer sur
    `production` pour l'étape manuelle)

## Déploiement sur 1&1

Voir le guide détaillé : [`docs/DEPLOY-1AND1.md`](docs/DEPLOY-1AND1.md). En résumé :

1. `composer dump-env prod` + `composer install --no-dev -o`.
2. `php bin/console sass:build && php bin/console asset-map:compile`.
3. Faire pointer le domaine sur le dossier `public/` (le `.htaccess` fourni gère la réécriture).
4. Renseigner `DATABASE_URL` (MySQL 8 de l'hébergement) et lancer les migrations.
5. Planifier les commandes cron `app:cve:sync` et `app:sites:scan`.

## Architecture

```
src/
├── Command/        app:cve:sync · app:sites:scan · app:user:create
├── Controller/     Security, Registration, Dashboard, Site, Advisory, Admin/User
├── Entity/         User · Site · Advisory · SiteAlert
├── Enum/           Technology · Severity · AlertType
├── Form/           RegistrationFormType · SiteType
├── Repository/     Doctrine repositories
├── Security/       AppUserChecker (approval) · Voter/SiteVoter
├── Service/
│   ├── Cve/        AdvisoryProviderInterface · OsvProvider · WordPressProvider · AdvisorySynchronizer
│   ├── Detection/  SiteScanner · TechnologyDetectorInterface · Detector/*
│   ├── Version/    VersionComparator · LatestVersionResolver
│   ├── Alert/      AlertEvaluator (cœur métier des alertes)
│   └── SiteMonitor scan + détection + évaluation des alertes (point d'entrée unique)
└── Twig/           AppExtension (compteurs sidebar)
```

Flux : `SiteMonitor::refresh()` → `SiteScanner` (techno+version) → `AlertEvaluator` qui compare la
version aux `Advisory` (via `VersionComparator`) et à la dernière version stable, puis crée/résout
les `SiteAlert`.
