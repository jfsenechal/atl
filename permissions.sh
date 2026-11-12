#!/bin/bash
#
# Lock down file permissions of the installation.
#
#   - Code and config: owned by OWNER, group WEB_GROUP, read-only for the web server
#     (dirs 750, files 640), nothing for "others".
#   - Writable dirs (cache, logs, uploads, image cache): read/write for both the web
#     server and OWNER (ACLs when setfacl is available, otherwise owned by WEB_USER, group-writable).
#   - Secrets and private data (.env*.local, data/, .git): OWNER only (or read-only for the web server for .env).
#
# Usage (as root):  sudo ./permissions.sh [WEB_USER] [OWNER]
#   WEB_USER  user running PHP (default: frankenphp if that user exists (dev), otherwise www-data (prod))
#   OWNER     user who deploys/edits the code (default: current owner of this directory)

set -euo pipefail

cd "$(dirname "$(readlink -f "$0")")"
ROOT=$(pwd)

# Dev runs FrankenPHP, prod runs nginx + php-fpm as www-data (dev also has www-data, so check frankenphp first).
if id frankenphp &>/dev/null; then
    DEFAULT_WEB_USER=frankenphp
else
    DEFAULT_WEB_USER=www-data
fi
WEB_USER=${1:-${WEB_USER:-$DEFAULT_WEB_USER}}
OWNER=${2:-${OWNER:-$(stat -c %U "$ROOT")}}

if [[ $EUID -ne 0 ]]; then
    echo "Run as root: sudo $0 [web_user] [owner]" >&2
    exit 1
fi
if ! id "$WEB_USER" &>/dev/null; then
    echo "Unknown web user: $WEB_USER" >&2
    exit 1
fi
if ! id "$OWNER" &>/dev/null; then
    echo "Unknown owner: $OWNER" >&2
    exit 1
fi
WEB_GROUP=$(id -gn "$WEB_USER")

# Directories PHP must write to.
WRITABLE=(
    var
    public/uploads
    public/media
    public/images
    public/assets
    public/qrcode
)

echo "Project : $ROOT"
echo "Owner   : $OWNER  |  web server: $WEB_USER:$WEB_GROUP"

# 1. Whole tree: owner rw, web group read-only, others nothing.
chown -R "$OWNER:$WEB_GROUP" "$ROOT"
find "$ROOT" -type d -exec chmod 750 {} +
find "$ROOT" -type f -exec chmod 640 {} +

# Executables.
chmod 750 bin/* ./*.sh
find vendor -path '*/bin/*' -type f -exec chmod 750 {} +

# 2. Secrets and private material: owner only (env files stay readable by PHP).
find "$ROOT" -maxdepth 1 -name '.env*' -type f -exec chmod 640 {} +
for private in .git data .idea .phpunit.cache; do
    if [[ -e $private ]]; then
        chown -R "$OWNER:$OWNER" "$private"
        chmod -R go-rwx "$private"
    fi
done

# 3. Writable directories.
for dir in "${WRITABLE[@]}"; do
    mkdir -p "$dir"
    if command -v setfacl &>/dev/null; then
        chown -R "$WEB_USER:$WEB_GROUP" "$dir"
        setfacl -R -m "u:$WEB_USER:rwX" -m "u:$OWNER:rwX" "$dir"
        setfacl -dR -m "u:$WEB_USER:rwX" -m "u:$OWNER:rwX" "$dir"
    else
        chown -R "$WEB_USER:$WEB_GROUP" "$dir"
        find "$dir" -type d -exec chmod 2770 {} +
        find "$dir" -type f -exec chmod 660 {} +
    fi
done

