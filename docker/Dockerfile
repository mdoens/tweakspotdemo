#syntax=docker/dockerfile:1.4

# Pin versions
FROM ghcr.io/shopware/docker-base:8.4 AS base-image
FROM ghcr.io/shopware/shopware-cli:latest-php-8.4 AS shopware-cli

# Build: composer install + admin JS + theme compile
FROM shopware-cli AS build

ADD . /src
WORKDIR /src

ENV SHOPWARE_CACHE_ID=docker

RUN --mount=type=ssh \
    --mount=type=cache,target=/root/.composer \
    --mount=type=cache,target=/root/.npm \
    /usr/local/bin/entrypoint.sh shopware-cli project ci /src

# Final production image
FROM base-image

ENV SHOPWARE_DISABLE_UPDATE_CHECK=1

COPY --from=build --chown=82 --link /src /var/www/html
