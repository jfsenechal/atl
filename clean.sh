#!/bin/bash
# Clear caches and logs, then reset permissions. Run as root: sudo ./clean.sh [WEB_USER] [OWNER]
set -euo pipefail
cd "$(dirname "$(readlink -f "$0")")"

rm -fr var/cache/prod
rm -fr var/cache/dev
rm -fr var/cache/rss
rm -fr var/log/*

./permissions.sh "$@"
