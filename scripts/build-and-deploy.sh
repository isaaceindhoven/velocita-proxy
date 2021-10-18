#!/bin/bash
set -euo pipefail

targetVersion=${1:-}
if [ -z "${targetVersion}" ]; then
    echo 'Error: no target version provided.'
    echo
    echo "Usage: $0 1.2.3"
    echo
    exit 1
fi

pushd $(dirname $0)/../proxy/ >/dev/null

imageName=isaaceindhoven/velocita-proxy

git tag "v${targetVersion}"
git push --tags

docker buildx build \
    --platform linux/amd64,linux/arm64,linux/arm/v7 \
    --tag ${imageName}:${targetVersion} \
    --tag ${imageName}:latest \
    --push \
    .
