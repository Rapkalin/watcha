#!/usr/bin/env bash
# =============================================================================
# In-place SSH deploy — Watcha (Symfony 7.4)
#
# Adapted from the anim-backend deploy. IONOS/1&1 shared hosting does NOT follow
# a symlink as the web docroot, so we deploy IN PLACE into a real directory
# <DEPLOY_DIR>/website (no releases/ + symlink). The 1&1 docroot must point to
#   <DEPLOY_DIR>/website/public
#
# Invoked by the deploy:* jobs (.github/workflows/deploy.yml). Runs on the CI
# runner and drives the remote server over SSH. Config from the environment:
#   DEPLOY_HOST / DEPLOY_USER / DEPLOY_PORT (=22) / DEPLOY_DIR (e.g. "prod-watcha")
#   DEPLOY_PHP_BIN (default /usr/bin/php8.3-cli)
#
# Server layout:
#   <DEPLOY_DIR>/
#     shared/                persistent, never overwritten by a deploy
#       .env                 env-specific secrets (symlinked into website/.env)
#       var/log/             logs, preserved across deploys
#       .htpasswd            OPTIONAL — its presence enables HTTP Basic auth
#     website/               the live app (rsynced in place; docroot=website/public)
#     old_website/           previous deploy, kept for rollback (hardlink snapshot)
#
# Rollback to the previous deploy (on the server, inside <DEPLOY_DIR>):
#     rm -rf website && cp -al old_website website
#     cd website && /usr/bin/php8.3-cli bin/console cache:clear
#   NB: rolls back CODE only — DB migrations already applied are NOT reverted
#   (keep migrations additive).
#
# Pre-requisites (one-time, by hand): shared/.env filled (incl. APP_ENV=prod,
# APP_SECRET, DATABASE_URL, optional WPSCAN_API_TOKEN).
# =============================================================================
set -euo pipefail

: "${DEPLOY_HOST:?DEPLOY_HOST is required}"
: "${DEPLOY_USER:?DEPLOY_USER is required}"
: "${DEPLOY_DIR:?DEPLOY_DIR is required}"
DEPLOY_PORT="${DEPLOY_PORT:-22}"
PHP_BIN="${DEPLOY_PHP_BIN:-/usr/bin/php8.3-cli}"

SSH="ssh -p ${DEPLOY_PORT} ${DEPLOY_USER}@${DEPLOY_HOST}"
WEBROOT="${DEPLOY_DIR}/website"

echo "▶ Deploying Watcha in place to ${DEPLOY_USER}@${DEPLOY_HOST}:${WEBROOT}"

# 1. Transition + rollback snapshot, in one SSH call:
#    - drop a leftover `website` SYMLINK from any old layout (else rsync writes
#      through it);
#    - snapshot the current website -> old_website via hardlinks (cp -al: file
#      contents are shared, not copied) so the previous deploy stays restorable;
#    - ensure website (real dir) + the shared skeleton exist.
$SSH "[ -L '${WEBROOT}' ] && rm -f '${WEBROOT}'; [ -d '${WEBROOT}' ] && { rm -rf '${DEPLOY_DIR}/old_website'; cp -al '${WEBROOT}' '${DEPLOY_DIR}/old_website'; }; mkdir -p '${WEBROOT}' '${DEPLOY_DIR}/shared/var/log'"

# 2. Sync the runtime tree in place. Build-only files are excluded; excluded
#    files already on the server (.env, var/) are protected from --delete.
rsync -az --delete \
    -e "ssh -p ${DEPLOY_PORT}" \
    --exclude-from="infra/deploy/rsync-exclude.txt" \
    ./ "${DEPLOY_USER}@${DEPLOY_HOST}:${WEBROOT}/"

echo "▶ Wiring shared files and warming the cache"

# 3. Wire shared files, (re)inject auth, warm the cache, run migrations.
#    Local vars (${...}) expand here; remote vars (\$...) on the server.
$SSH bash -se <<EOF
set -euo pipefail
BASE="\$(cd "${DEPLOY_DIR}" && pwd)"   # absolute, for robust symlink targets
cd "\$BASE/website"

# Environment-specific file (never shipped in the artifact): real prod .env.
ln -sfn "\$BASE/shared/.env" .env
mkdir -p var && rm -rf var/log && ln -sfn "\$BASE/shared/var/log" var/log

# Fail fast (with a clear message) if shared/.env is not production-configured. Otherwise the
# kernel boots in dev and tries to load dev-only bundles (e.g. MakerBundle) that --no-dev omits.
if [ ! -f .env ]; then
  echo "✖ \$BASE/shared/.env is missing — create it (APP_ENV=prod, APP_SECRET, DATABASE_URL, ...)"; exit 1;
fi
if ! grep -qE '^[[:space:]]*APP_ENV[[:space:]]*=[[:space:]]*prod' .env; then
  echo "✖ \$BASE/shared/.env must define APP_ENV=prod"; exit 1;
fi

# Per-env HTTP Basic auth: appended to public/.htaccess only if shared/.htpasswd
# exists. public/.htaccess is rsynced fresh each deploy, so no duplication.
if [ -f "\$BASE/shared/.htpasswd" ]; then
  {
    echo ''
    echo '# --- HTTP Basic auth (injected by deploy) ---'
    echo 'AuthType Basic'
    echo 'AuthName "Restricted area"'
    echo "AuthUserFile \$BASE/shared/.htpasswd"
    echo 'Require valid-user'
  } >> public/.htaccess
fi

# Build the prod cache (APP_ENV comes from the linked shared/.env) then migrate.
${PHP_BIN} bin/console cache:clear  --no-interaction
${PHP_BIN} bin/console cache:warmup --no-interaction
${PHP_BIN} bin/console doctrine:migrations:migrate --no-interaction --all-or-nothing
EOF

echo "✔ Watcha deployed in place at ${DEPLOY_DIR}/website."
