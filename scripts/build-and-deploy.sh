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

pushd $(dirname $0)/../

dockerComposeFiles='-f docker-compose.yml -f docker-compose.build.yml'

buildAndPush() {
    local version=$1
    BUILD_VERSION=${version} docker-compose ${dockerComposeFiles} build
    BUILD_VERSION=${version} docker-compose ${dockerComposeFiles} push
}

git tag "v${targetVersion}"
git push --tags

buildAndPush "${targetVersion}"
buildAndPush latest
