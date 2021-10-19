#!/bin/ash
set -eu

function renderTemplate() {
    local source=$1
    local target=$2
    erb -U -T '-' "${source}" > "${target}"
}

renderTemplate /usr/local/lib/velocita/proxy.conf.erb /etc/nginx/conf.d/proxy.conf

mkdir -p /var/www/html
renderTemplate /usr/local/lib/velocita/index.html.erb /var/www/html/index.html
renderTemplate /usr/local/lib/velocita/mirrors.json.erb /var/www/html/mirrors.json

exec "$@"
