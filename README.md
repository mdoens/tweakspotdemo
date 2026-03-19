# Tweakspot Hosting

Shopware 6.7 production hosting for [tweakspot.db.strix.tools](https://tweakspot.db.strix.tools).

Includes the **Visual Merchandiser Pro** plugin via Composer from Bitbucket.

## How it works

```
composer.json
  → requires strix/visual-merchandiser (from bitbucket.org/sition_nl/tweakspot)
  → requires shopware/core ~6.7.0

docker/Dockerfile
  → shopware-cli project ci (composer install + admin JS build + theme compile)
  → everything baked into one image

docker-compose.yml
  → Shopware (FrankenPHP) + MariaDB + Redis + OpenSearch
```

## Deploy

```bash
# Build image (includes plugin + admin JS + compiled theme)
docker compose build --ssh default

# Start
docker compose up -d

# First time: run Shopware setup
docker compose exec shopware setup
```

## On Coolify

Push to Bitbucket → Coolify auto-builds from `docker/Dockerfile`.

The SSH key `scout-deploy` must have access to both repos:
- `sition_nl/tweakspot-hosting` (this repo)
- `sition_nl/tweakspot` (plugin repo, composer dependency)

## Plugin repo

The plugin source lives at: `bitbucket.org/sition_nl/tweakspot`

Local development with DDEV: see that repo's README.
