# Tweakspot Hosting

**Live:** https://tweakspot.db.strix.tools
**Admin:** https://tweakspot.db.strix.tools/admin (`admin` / `shopware`)

Shopware 6.7 production hosting met Visual Merchandiser Pro plugin.

## Repos

| Repo | URL | Doel |
|------|-----|------|
| **Plugin** | `bitbucket.org/sition_nl/tweakspot` | Plugin code + DDEV local dev |
| **Hosting** | `github.com/mdoens/tweakspotdemo` | Dit repo — Shopware project + Dockerfile |

## Architectuur

```
Dockerfile (dunglas/frankenphp:1-php8.3)
  → composer install --no-scripts (plugin als path dependency)
  → bin/ci (admin JS + storefront theme build, memory_limit=512M)
  → .env.prod → .env (met Coolify DB hostname)
  → entrypoint.sh:
    - Source .env als DATABASE_URL niet als container env gezet
    - Check DB table count (skip install als >10 tabellen)
    - system:install --create-database --basic-setup --force
    - plugin:refresh + plugin:install --activate
    - cache:clear
    - exec frankenphp run
```

## Deploy

```bash
# 1. Code wijzigen + pushen
git push github main

# 2. In Coolify dashboard: tweakspot → Deploy
# Of via API:
curl -sf -X GET "https://cool1.db.strix.tools/api/v1/applications/c15zcizrvvuxdzsgqbk2m8pp/start" \
  -H "Authorization: Bearer ${COOLIFY_TOKEN}" -H "Accept: application/json"

# 3. Wacht ~5 min (Docker build + entrypoint)

# 4. Eerste keer: domain mapping + assets + fixtures
#    (zie "Na verse installatie" hieronder)
```

## Coolify

| Resource | UUID | Type |
|----------|------|------|
| App | `c15zcizrvvuxdzsgqbk2m8pp` | Application (Dockerfile from GitHub) |
| DB | `wxg02q1r6z1r82dmycff199x` | MariaDB 11 (Coolify managed) |
| Server | `cool1.db.strix.tools` | SSH: `root@cool1.db.strix.tools` |

Dashboard: https://cool1.db.strix.tools

## Na verse installatie

```bash
# SSH naar server
ssh root@cool1.db.strix.tools

# Find container
APP=$(docker ps --format '{{.Names}}' | grep c15zcizrvvuxdzsgqbk2m8pp | head -1)

# Assets installeren (admin JS bundles)
docker exec $APP php -d memory_limit=512M bin/console assets:install

# Trusted proxies (HTTPS detectie achter Traefik)
docker exec $APP bash -c 'mkdir -p /app/config/packages && cat > /app/config/packages/trusted_proxies.yaml << EOF
framework:
    trusted_proxies: "REMOTE_ADDR"
    trusted_headers: ["x-forwarded-for","x-forwarded-host","x-forwarded-proto","x-forwarded-port"]
EOF'

# Cache clear
docker exec $APP php -d memory_limit=512M bin/console cache:clear
```

Domain mapping + fixtures via Shopware API (of via admin UI).

## Fixtures (demo data)

192 producten (48/categorie), 4 rules, 7 pins. Seed script in het deploy commando of via de plugin repo's DDEV seeders.

## Learnings

| Issue | Fix |
|-------|-----|
| PHP memory 128MB te weinig | `memory_limit=512M` in Dockerfile + runtime |
| `composer install` auto-scripts crashen | `--no-scripts` (needs Symfony kernel) |
| `system:install` crasht op "admin exists" | Check DB table count, niet install.lock |
| Coolify env vars overschrijven `.env` niet | Gebruik `.env.prod` in Dockerfile |
| Coolify API maakt geen DB records | Resources via dashboard aanmaken |
| Admin laadt niet (http:// links) | Trusted proxies YAML configureren |
| Storefront "Sales Channel Not Found" | Beide http + https domains toevoegen |
| FrankenPHP ACME challenge 404 | Coolify beheert Traefik labels automatisch |
| Bitbucket SSH builds falen | GitHub gebruiken (Coolify bug met Bitbucket) |
| ARM image op AMD64 server | `docker buildx --platform linux/amd64` |
