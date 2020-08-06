#!/bin/ash
set -eu

# Generate proxy sites configuration
erb -U -T '-' /root/templates/proxy.conf.erb > /etc/nginx/conf.d/proxy.conf

# Generate mirror configuration
mkdir -p /var/www/html
erb -U -T '-' /root/templates/mirrors.json.erb > /var/www/html/mirrors.json

exec "$@"
