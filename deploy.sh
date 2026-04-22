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

# Parse key=value pairs from .env (ignores comments and blank lines)
get_env() {
    grep -E "^$1=" "$ENV_FILE" | head -1 | cut -d '=' -f2- | tr -d '"' | tr -d "'"
}

APP_DIR="$(get_env APP_DIR)"
APP_URL="$(get_env APP_URL)"
GIT_BRANCH="$(get_env GIT_BRANCH)"
# -----------------------------------------------------------------------------

# ---- Configuration ----------------------------------------------------------
DEPLOY_DIR="${APP_DIR:-$SCRIPT_DIR}"
BRANCH="${GIT_BRANCH:-main}"
# Auto-detect PHP-FPM version
PHP_VERSION=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
PHP_FPM_SERVICE="php${PHP_VERSION}-fpm"
NGINX_SERVICE="nginx"
WEB_USER="www-data"
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

# ---- Put app in maintenance mode -------------------------------------------
log "Enabling maintenance mode..."
php artisan down --retry=15 --render="errors::503" 2>/dev/null || warn "Maintenance mode not activated (non-fatal)"

# Make sure we bring the app back up on any exit (success or failure)
trap 'php artisan up >/dev/null 2>&1 || true' EXIT

# ---- Git safe directory (needed when running as root via sudo) --------------
git config --global --add safe.directory "$DEPLOY_DIR"

# ---- Git pull ----------------------------------------------------------------
log "Fetching latest code from branch: $BRANCH"
git fetch origin
git reset --hard "origin/$BRANCH"
git pull origin "$BRANCH"
success "Code updated"

# ---- Composer ---------------------------------------------------------------
log "Installing PHP dependencies (no-dev)..."
composer install --no-dev --optimize-autoloader --no-interaction --quiet
success "Composer done"

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
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
success "Laravel optimized"

# ---- Database migrations ----------------------------------------------------
log "Running database migrations..."
php artisan migrate --force
success "Migrations complete"

# ---- Storage link (safe — skips if already linked) --------------------------
php artisan storage:link 2>/dev/null || warn "Storage link already exists or failed (non-fatal)"

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
php artisan up
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
