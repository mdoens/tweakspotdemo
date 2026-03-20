# Tweakspot Hosting

**Live:** https://tweakspot.db.strix.tools | **Admin:** `/admin` (`admin` / `shopware`)

Shopware 6.7 + Visual Merchandiser Pro op Coolify.

## Repos

| Repo | Doel |
|------|------|
| `github.com/mdoens/tweakspotdemo` | Dit repo — Shopware project + Dockerfile (Coolify bouwt hiervan) |
| `bitbucket.org/sition_nl/tweakspot` | Plugin code + DDEV lokale development |

## Deploy

```bash
git push github main
# Coolify dashboard → tweakspot → Deploy
```

Build duurt ~5 min. Entrypoint installeert Shopware + activeert plugin automatisch.

## Stack

| Component | Image |
|-----------|-------|
| Shopware | `dunglas/frankenphp:1-php8.3` + `Dockerfile` |
| Database | Coolify managed MariaDB (`wxg02q1r6z1r82dmycff199x`) |
| Coolify | `cool1.db.strix.tools` (SSH: `root@cool1.db.strix.tools`) |

App UUID: `c15zcizrvvuxdzsgqbk2m8pp`

## Na verse installatie (eenmalig)

```bash
ssh root@cool1.db.strix.tools
APP=$(docker ps --format '{{.Names}}' | grep c15zcizrvvuxdzsgqbk2m8pp | head -1)

# Assets + trusted proxies + cache
docker exec $APP php -d memory_limit=512M bin/console assets:install
docker exec $APP bash -c 'mkdir -p /app/config/packages && printf "framework:\n    trusted_proxies: \"REMOTE_ADDR\"\n    trusted_headers: [\"x-forwarded-for\",\"x-forwarded-host\",\"x-forwarded-proto\",\"x-forwarded-port\"]\n" > /app/config/packages/trusted_proxies.yaml'
docker exec $APP php -d memory_limit=512M bin/console cache:clear

# Domain mapping: voeg https + http toe aan Storefront sales channel via admin UI
# Onboarding uitzetten: Settings → System Config → core.frw.completedAt
```

## Goed om te weten

- **PHP memory_limit=512M** — standaard 128M is te weinig voor Shopware
- **`composer install --no-scripts`** — auto-scripts vereisen database die er bij build niet is
- **`.env.prod`** bevat de Coolify DB hostname — wordt in Dockerfile naar `.env` gekopieerd
- **GitHub voor Coolify** — Bitbucket SSH builds werken niet in Coolify v4 beta
- **Entrypoint checkt DB tabellen** — als >10 tabellen: skip install (voorkomt "admin exists" crash)
