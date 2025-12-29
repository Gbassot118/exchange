# Squelette Symfony 8.0

Squelette d'application web basé sur [Symfony 8.0](https://symfony.com) avec [Docker](https://www.docker.com/), [FrankenPHP](https://frankenphp.dev) et [Caddy](https://caddyserver.com/).

## Technologies

| Composant | Version | Description |
|-----------|---------|-------------|
| PHP | 8.4 | Via FrankenPHP |
| Symfony | 8.0 | Framework PHP |
| PostgreSQL | 16 | Base de données |
| Caddy | Latest | Serveur web avec HTTPS automatique |
| Doctrine ORM | 3.5 | Mapping objet-relationnel |
| Twig | 3.x | Moteur de templates |
| Mercure | Intégré | Temps réel (WebSockets) |

## Prérequis

- [Docker](https://docs.docker.com/get-docker/) (v20.10+)
- [Docker Compose](https://docs.docker.com/compose/install/) (v2.10+)

## Installation

### 1. Cloner le projet

```bash
git clone git@github.com:Gbassot118/gestion.git
cd gestion
```

### 2. Configurer l'environnement

Copier et adapter le fichier d'environnement :

```bash
cp .env .env.local
```

Variables importantes à configurer dans `.env.local` :

```env
# Base de données
POSTGRES_PASSWORD=votre_mot_de_passe_securise
DATABASE_URL="postgresql://app:votre_mot_de_passe_securise@database:5432/app?serverVersion=16&charset=utf8"

# Clés API (optionnel)
OPENAI_API_KEY=sk-xxx
ANTHROPIC_API_KEY=sk-ant-xxx
```

### 3. Démarrer l'application

```bash
# Construire les images Docker
docker compose build --pull --no-cache

# Démarrer les services
docker compose up -d --wait
```

### 4. Accéder à l'application

Ouvrir [https://localhost](https://localhost) dans votre navigateur.

> Le certificat HTTPS est auto-généré. Acceptez-le dans votre navigateur.

## Structure du projet

```
gestion/
├── assets/                 # Assets frontend (JS, CSS)
├── bin/
│   └── console            # Console Symfony
├── config/
│   ├── packages/          # Configuration des bundles
│   │   ├── ai.yaml        # Configuration IA (Claude/OpenAI)
│   │   ├── doctrine.yaml  # Configuration Doctrine
│   │   ├── security.yaml  # Configuration sécurité
│   │   └── ...
│   ├── routes/            # Configuration des routes
│   ├── bundles.php        # Bundles activés
│   ├── routes.yaml        # Routes principales
│   └── services.yaml      # Services et injection de dépendances
├── frankenphp/
│   ├── Caddyfile          # Configuration Caddy
│   ├── conf.d/            # Configuration PHP
│   └── docker-entrypoint.sh
├── migrations/            # Migrations Doctrine
├── public/
│   └── index.php          # Point d'entrée
├── src/
│   ├── Controller/        # Contrôleurs
│   ├── Entity/            # Entités Doctrine
│   ├── Repository/        # Repositories
│   └── Kernel.php         # Kernel Symfony
├── templates/             # Templates Twig
├── var/                   # Cache et logs
├── vendor/                # Dépendances (gitignore)
├── .env                   # Variables d'environnement
├── compose.yaml           # Configuration Docker principale
├── compose.override.yaml  # Surcharge développement
├── compose.prod.yaml      # Surcharge production
├── Dockerfile             # Build multi-stage
└── composer.json          # Dépendances PHP
```

## Configuration

### Variables d'environnement

| Variable | Description | Défaut |
|----------|-------------|--------|
| `APP_ENV` | Environnement (dev/prod) | `dev` |
| `APP_SECRET` | Clé secrète Symfony | - |
| `DATABASE_URL` | URL de connexion PostgreSQL | - |
| `POSTGRES_USER` | Utilisateur PostgreSQL | `app` |
| `POSTGRES_PASSWORD` | Mot de passe PostgreSQL | `!ChangeMe!` |
| `POSTGRES_DB` | Nom de la base | `app` |
| `OPENAI_API_KEY` | Clé API OpenAI | - |
| `ANTHROPIC_API_KEY` | Clé API Anthropic | - |

### Configuration Docker

**Développement** (`compose.override.yaml`) :
- Hot reload activé (watch mode)
- XDebug disponible
- Volumes montés pour le code source

**Production** (`compose.prod.yaml`) :
- OPcache préchargé
- Dépendances de dev exclues
- Image optimisée

## Développement

### Commandes utiles

```bash
# Accéder au conteneur PHP
docker compose exec php bash

# Console Symfony
docker compose exec php bin/console

# Créer une entité
docker compose exec php bin/console make:entity

# Créer un contrôleur
docker compose exec php bin/console make:controller

# Créer une migration
docker compose exec php bin/console make:migration

# Exécuter les migrations
docker compose exec php bin/console doctrine:migrations:migrate

# Vider le cache
docker compose exec php bin/console cache:clear

# Voir les routes
docker compose exec php bin/console debug:router
```

### Debugging avec XDebug

XDebug est préconfiguré mais désactivé par défaut. Pour l'activer :

```bash
XDEBUG_MODE=debug docker compose up -d
```

Configuration IDE (PHPStorm/VSCode) :
- Port : 9003
- IDE Key : PHPSTORM
- Path mappings : `/app` → répertoire du projet

### Hot Reload

En développement, FrankenPHP surveille les fichiers et redémarre automatiquement le worker PHP lors des modifications.

## Production

### Build de l'image

```bash
docker compose -f compose.yaml -f compose.prod.yaml build
```

### Variables requises

```env
APP_ENV=prod
APP_SECRET=votre_secret_securise
DATABASE_URL=postgresql://user:password@host:5432/database
CADDY_MERCURE_JWT_SECRET=votre_secret_mercure
```

### Déploiement

```bash
docker compose -f compose.yaml -f compose.prod.yaml up -d
```

## Fonctionnalités incluses

### Doctrine ORM

ORM configuré avec PostgreSQL. Mapping par attributs PHP 8.

```php
#[ORM\Entity(repositoryClass: UserRepository::class)]
class User
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;
}
```

### Système de sécurité

Framework de sécurité Symfony préconfiguré :
- Provider en mémoire (à remplacer par une entité User)
- Protection CSRF
- Firewall configuré

### Templates Twig

Moteur de templates Twig prêt à l'emploi.

```twig
{# templates/base.html.twig #}
<!DOCTYPE html>
<html>
    <head>
        <title>{% block title %}{% endblock %}</title>
    </head>
    <body>
        {% block body %}{% endblock %}
    </body>
</html>
```

### Symfony AI Bundle

Intégration IA préconfigurée avec Anthropic (Claude) et OpenAI.

Configuration dans `config/packages/ai.yaml` :
- Plateforme par défaut : Anthropic Claude Sonnet 4.5
- Clés API via variables d'environnement

### Mercure (Temps réel)

Hub Mercure intégré pour les communications temps réel (notifications, chat, etc.).

## Arrêt des services

```bash
docker compose down --remove-orphans
```

## Licence

MIT
