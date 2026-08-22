#!/usr/bin/env bash
# Start kontejneru: klon/pull kódu z Gitu do volume → počkej na DB →
# založ schéma + admin účet → spusť Apache.
# Self-update: restart kontejneru (nebo appky v TrueNAS) udělá `git pull`.
set -uo pipefail

REPO_URL="${REPO_URL:-https://github.com/DomaciBlbosti/VyukovyPortal.git}"
BRANCH="${REPO_BRANCH:-main}"
UPDATE_ON_START="${UPDATE_ON_START:-true}"
WEBROOT=/var/www/html
cd "$WEBROOT"

if [ ! -d .git ]; then
  echo "[init] klonuji ${REPO_URL} (${BRANCH})"
  git clone --branch "${BRANCH}" "${REPO_URL}" /tmp/repo \
    && cp -a /tmp/repo/. "$WEBROOT"/ && rm -rf /tmp/repo \
    || { echo "[init] klon selhal"; sleep 10; exit 1; }
fi
git config --global --add safe.directory "$WEBROOT"

if [ "${UPDATE_ON_START}" = "true" ]; then
  before="$(git rev-parse HEAD 2>/dev/null || echo none)"
  git fetch origin "${BRANCH}" 2>&1 || echo "[git] fetch přeskočen (offline?)"
  git checkout "${BRANCH}" 2>/dev/null || true
  git pull --ff-only origin "${BRANCH}" 2>&1 || echo "[git] pull přeskočen"
  after="$(git rev-parse HEAD 2>/dev/null || echo none)"
  [ "$before" != "$after" ] && echo "[git] aktualizováno ${before:0:7} → ${after:0:7}"
fi

chown -R www-data:www-data "$WEBROOT"

echo "[db] čekám na databázi a zakládám schéma"
php "$WEBROOT/docker/init-db.php" || { echo "[db] inicializace selhala"; exit 1; }

echo "[run] start Apache na :80 (commit $(git rev-parse --short HEAD 2>/dev/null || echo '?'))"
exec apache2-foreground
