#!/usr/bin/env bash
# tools/docker.sh <php patch version> - runs tools/ci.sh in a php:<patch>-cli container over a copy of the repository
# (source mounted read-only and copied with cp -a, not a writable bind mount).
# Examples: bash tools/docker.sh 8.2.35        DEPS=lowest bash tools/docker.sh 8.5.12        bash tools/docker.sh 8.3.0   (must fail in step 1)
set -euo pipefail
PATCH="${1:?usage: tools/docker.sh <php patch version, e.g. 8.2.35>}"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CACHE="${COMPOSER_CACHE_HOST:-$HOME/.cache/composer-docker}"
mkdir -p "$CACHE"
if command -v cygpath >/dev/null 2>&1; then
	ROOT="$(cygpath -m "$ROOT")"
	CACHE="$(cygpath -m "$CACHE")"
fi
MSYS_NO_PATHCONV=1 docker run --rm -e DEPS="${DEPS:-highest}" \
	-v "$ROOT:/src:ro" -v "$CACHE:/root/.cache/composer" "php:${PATCH}-cli" \
	bash -c 'mkdir /app && cp -a /src/. /app && cd /app && rm -rf vendor composer.lock phpunit.xml phpstan.neon phpcs.xml .phpunit.cache && bash tools/ci.sh'
