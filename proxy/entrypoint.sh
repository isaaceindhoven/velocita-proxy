#!/bin/ash
set -eu

# Generate proxy sites configuration
erb -U -T '-' /usr/local/lib/velocita/proxy.conf.erb > /etc/nginx/conf.d/proxy.conf

# Generate mirror configuration
mkdir -p /var/www/html
erb -U -T '-' /usr/local/lib/velocita/mirrors.json.erb > /var/www/html/mirrors.json

exec "$@"
