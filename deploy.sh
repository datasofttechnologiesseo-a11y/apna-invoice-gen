#!/bin/bash

# =============================================================================
# Deployment Script — Apna Invoice
# Usage: Run this script ON the server, or via SSH:
#   ssh user@server 'bash /var/www/html/apna-invoice/deploy.sh'
# =============================================================================

set -euo pipefail

# ---- Load values from .env --------------------------------------------------
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="$SCRIPT_DIR/.env"

if [ ! -f "$ENV_FILE" ]; then
    echo "ERROR: .env file not found at $ENV_FILE" >&2
    exit 1
fi

# Parse key=value pairs from .env (ignores comments and blank lines).
# A missing key gives an empty value; without `|| true` set -e would end the
# script silently before the defaults below could apply.
get_env() {
    grep -E "^$1=" "$ENV_FILE" | head -1 | cut -d '=' -f2- | tr -d '"' | tr -d "'" | tr -d '\r' || true
}

APP_DIR="$(get_env APP_DIR)"
APP_URL="$(get_env APP_URL)"
GIT_BRANCH="$(get_env GIT_BRANCH)"
# -----------------------------------------------------------------------------

# ---- Configuration ----------------------------------------------------------
DEPLOY_DIR="${APP_DIR:-$SCRIPT_DIR}"
BRANCH="${GIT_BRANCH:-main}"
NGINX_SERVICE="nginx"
NGINX_CONF_DIRS="${NGINX_CONF_DIRS:-/etc/nginx/sites-enabled /etc/nginx/conf.d}"
WEB_USER="www-data"
MIN_PHP_VERSION_ID=80200   # keep in step with "php" in composer.json
# -----------------------------------------------------------------------------

# ---- Which PHP serves the website -------------------------------------------
# The command-line `php` can be newer than the PHP-FPM that nginx serves the
# site with. Composer has to run under the website's PHP: run under a newer
# one, an update can install packages the site cannot load, and every page
# shows "Composer detected issues in your platform". Order of preference:
# DEPLOY_PHP_VERSION in .env, the socket in this site's nginx config, the only
# running php-fpm service, then the command-line php.
detect_web_php_version() {
    local found conf_files

    found="$(get_env DEPLOY_PHP_VERSION)"
    if [ -n "$found" ]; then echo "$found"; return; fi

    # shellcheck disable=SC2086
    conf_files="$(grep -rlF "$DEPLOY_DIR" $NGINX_CONF_DIRS 2>/dev/null || true)"
    if [ -n "$conf_files" ]; then
        # shellcheck disable=SC2086
        found="$(grep -hoE 'php[0-9]+\.[0-9]+-fpm' $conf_files 2>/dev/null | grep -oE '[0-9]+\.[0-9]+' | sort -u || true)"
        if [ "$(echo "$found" | wc -w)" -eq 1 ]; then echo "$found"; return; fi
    fi

    found="$(systemctl list-units --type=service --state=running --no-legend 'php*-fpm.service' 2>/dev/null \
        | grep -oE 'php[0-9]+\.[0-9]+-fpm' | grep -oE '[0-9]+\.[0-9]+' | sort -u || true)"
    if [ "$(echo "$found" | wc -w)" -eq 1 ]; then echo "$found"; return; fi

    php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;'
}
# -----------------------------------------------------------------------------

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

log()    { echo -e "${CYAN}[DEPLOY]${NC} $1"; }
success(){ echo -e "${GREEN}[OK]${NC}     $1"; }
warn()   { echo -e "${YELLOW}[WARN]${NC}   $1"; }
error()  { echo -e "${RED}[ERROR]${NC}  $1"; exit 1; }

log "Starting deployment to $DEPLOY_DIR"
START_TIME=$(date +%s)

# ---- Validate directory -----------------------------------------------------
[ -d "$DEPLOY_DIR" ] || error "Deploy directory not found: $DEPLOY_DIR"
cd "$DEPLOY_DIR"

# ---- Use the website's PHP for everything below -----------------------------
WEB_PHP_VERSION="$(detect_web_php_version)"
PHP_BIN="$(command -v "php${WEB_PHP_VERSION}" || command -v php)"
PHP_BIN_VERSION="$("$PHP_BIN" -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
[ "$PHP_BIN_VERSION" = "$WEB_PHP_VERSION" ] \
    || error "The website runs PHP $WEB_PHP_VERSION but there is no php${WEB_PHP_VERSION} command here (found PHP $PHP_BIN_VERSION). Install php${WEB_PHP_VERSION}-cli, or set DEPLOY_PHP_VERSION in .env."
"$PHP_BIN" -r "exit(PHP_VERSION_ID >= $MIN_PHP_VERSION_ID ? 0 : 1);" \
    || error "The website runs PHP $WEB_PHP_VERSION. Apna Invoice needs PHP 8.2 or newer. Switch the site to PHP 8.2+ and deploy again."
PHP_FPM_SERVICE="php${WEB_PHP_VERSION}-fpm"

COMPOSER_BIN="$(command -v composer)" || error "composer not found"
# Run Composer under the website's PHP. A wrapper script (not PHP) is run as is.
run_composer() {
    if head -c 512 "$COMPOSER_BIN" | grep -qE '^#!.*php|<\?php|__HALT_COMPILER'; then
        "$PHP_BIN" "$COMPOSER_BIN" "$@"
    else
        warn "composer is not a PHP file; it may run under a different PHP than the website"
        "$COMPOSER_BIN" "$@"
    fi
}
success "Using PHP $WEB_PHP_VERSION ($PHP_BIN), same as the website"

# ---- Put app in maintenance mode -------------------------------------------
log "Enabling maintenance mode..."
"$PHP_BIN" artisan down --retry=15 --render="errors::503" 2>/dev/null || warn "Maintenance mode not activated (non-fatal)"

# Make sure we bring the app back up on any exit (success or failure)
trap '"$PHP_BIN" artisan up >/dev/null 2>&1 || true' EXIT

# ---- Git safe directory (needed when running as root via sudo) --------------
git config --global --add safe.directory "$DEPLOY_DIR"

# ---- Git pull ----------------------------------------------------------------
log "Fetching latest code from branch: $BRANCH"
git fetch origin
git reset --hard "origin/$BRANCH"
git pull origin "$BRANCH"
success "Code updated"

# ---- Composer ---------------------------------------------------------------
# Always `install` (exact versions from composer.lock), never `update`, on the
# server. This also repairs a vendor folder left behind by a manual update.
log "Installing PHP dependencies (no-dev) from composer.lock..."
run_composer install --no-dev --optimize-autoloader --no-interaction --quiet
"$PHP_BIN" -r 'require "vendor/autoload.php";' >/dev/null 2>&1 \
    || error "The installed packages do not load on PHP $WEB_PHP_VERSION. Check: $PHP_BIN -r 'require \"vendor/autoload.php\";'  (see DEPLOY.md, Troubleshooting)"
success "Composer done, packages load on PHP $WEB_PHP_VERSION"

# ---- Node / Vite build ------------------------------------------------------
if [ -f "package.json" ]; then
    log "Installing Node dependencies..."
    npm ci --prefer-offline --quiet
    log "Building frontend assets (vite)..."
    npm run build
    success "Frontend assets built"
fi

# ---- Laravel optimizations --------------------------------------------------
log "Caching config, routes and views..."
"$PHP_BIN" artisan config:clear
"$PHP_BIN" artisan route:clear
"$PHP_BIN" artisan view:clear
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache
"$PHP_BIN" artisan optimize
success "Laravel optimized"

# ---- Database migrations ----------------------------------------------------
log "Running database migrations..."
"$PHP_BIN" artisan migrate --force
success "Migrations complete"

# ---- Storage link (safe — skips if already linked) --------------------------
"$PHP_BIN" artisan storage:link 2>/dev/null || warn "Storage link already exists or failed (non-fatal)"

# ---- Permissions ------------------------------------------------------------
log "Setting directory permissions..."
sudo chown -R "$WEB_USER:$WEB_USER" storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
success "Permissions set"

# ---- Restart services -------------------------------------------------------
log "Restarting $PHP_FPM_SERVICE..."
sudo systemctl restart "$PHP_FPM_SERVICE" || error "Failed to restart $PHP_FPM_SERVICE"
success "$PHP_FPM_SERVICE restarted"

log "Reloading $NGINX_SERVICE..."
sudo systemctl reload "$NGINX_SERVICE" || sudo systemctl restart "$NGINX_SERVICE" || error "Failed to restart $NGINX_SERVICE"
success "$NGINX_SERVICE reloaded"

# ---- Bring app back up ------------------------------------------------------
log "Disabling maintenance mode..."
"$PHP_BIN" artisan up
success "App is live"

# ---- Done -------------------------------------------------------------------
END_TIME=$(date +%s)
ELAPSED=$((END_TIME - START_TIME))
echo ""
echo -e "${GREEN}=================================================${NC}"
echo -e "${GREEN}  Deployment complete in ${ELAPSED}s${NC}"
echo -e "${GREEN}  Branch : $BRANCH${NC}"
echo -e "${GREEN}  Dir    : $DEPLOY_DIR${NC}"
echo -e "${GREEN}  URL    : $APP_URL${NC}"
echo -e "${GREEN}=================================================${NC}"
