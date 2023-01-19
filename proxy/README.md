# Velocita Proxy Docker image

[Velocita](https://github.com/isaaceindhoven/velocita-proxy) acts as a caching reverse proxy to Composer repositories.

Combined with [composer-velocita](https://github.com/isaaceindhoven/composer-velocita), it increases the performance
of a `composer install` when a local cache is not yet available and makes sure packages can be downloaded even if the
source location is experiencing issues.

## Running the proxy

Start a proxy for Packagist and GitHub, listening on port `80`:

```
docker run -d --name velocita -p 80:8080 \
    -e MIRROR_PACKAGIST_URL=https://repo.packagist.org \
    -e MIRROR_PACKAGIST_TYPE=composer \
    -e MIRROR_GITHUB_CODELOAD_URL=https://codeload.github.com \
    isaaceindhoven/velocita-proxy
```

Go ahead and visit `http://localhost/mirror/packagist/packages.json` - this should now give you the Packagist index!

## Configuring Velocita

Velocita configures itself with the environment variables you pass.

| Environment variable   | Required | Type                              | Description                            |
| ---------------------- | -------- | --------------------------------- | -------------------------------------- |
| `VELOCITA_URL`         | No       | URL (default: `http://localhost`) | The URL at which Velocita is reachable |
| `VELOCITA_TLS_ENABLED` | No       | One of: `true`, `false` (default) | Enable HTTPS                           |
| `VELOCITA_AUTH_TYPE`   | No       | One of: `none` (default), `basic` | Enable HTTP authentication             |

## Configuring mirrors

A mirror is added if you pass a variable of the form `MIRROR_{name}_URL`, and is configured with additional
`MIRROR_{name}_*` variables.

When you add a Composer repository, make sure you set `MIRROR_{name}_TYPE` to `composer` so the correct cache
invalidation is applied.

For example, this will proxy two different Composer repositories:

```
docker run -d --name velocita -p 80:8080 \
    -e MIRROR_WPACKAGIST_URL=https://wpackagist.org \
    -e MIRROR_WPACKAGIST_TYPE=composer \
    -e MIRROR_FIREGENTO_URL=https://packages.firegento.com \
    -e MIRROR_FIREGENTO_TYPE=composer \
    isaaceindhoven/velocita-proxy
```

For every mirror, the following configuration options are available:

| Environment variable                 | Required | Type                                   | Description                              |
| ------------------------------------ | -------- | -------------------------------------- | ---------------------------------------- |
| `MIRROR_{name}_URL`                  | Yes      | URL                                    | The URL of the upstream server to proxy  |
| `MIRROR_{name}_TYPE`                 | No       | One of: `simple` (default), `composer` | The mirror type                          |
| `MIRROR_{name}_CACHE_EXPIRY`         | No       | Time (default: `3650d`)                | Time after which cached items expire     |
| `MIRROR_{name}_CACHE_SIZE`           | No       | Size (default: `1g`)                   | Maximum size of this mirror's cache      |
| `MIRROR_{name}_ALLOW_REVALIDATE`     | No       | Boolean (default: `false`)             | Allow revalidation of cached items       |
| `MIRROR_{name}_PACKAGES_JSON_EXPIRY` | No       | Time (default: `2m`)                   | Time after which `packages.json` expires |
| `MIRROR_{name}_AUTH_TYPE`            | No       | One of: `basic` (default), `bearer`    | Type of upstream authentication          |
| `MIRROR_{name}_AUTH_USERNAME`        | No       | String                                 | Username for authentication              |
| `MIRROR_{name}_AUTH_PASSWORD`        | No       | String                                 | Password or token for authentication     |
| `MIRROR_{name}_USER_AGENT`           | No       | String                                 | User Agent header sent to upstream       |

For time and size unit syntax, see: http://nginx.org/en/docs/syntax.html

## Configuring the storage volume

Inside the container, all cache files are stored in a volume mounted on `/var/cache/velocita`. Use `-v` to mount this
volume somewhere on your host's filesystem:

```
docker run -d --name velocita -p 80:8080 \
    -e MIRROR_PACKAGIST_URL=https://repo.packagist.org \
    -e MIRROR_PACKAGIST_TYPE=composer \
    -v /path/on/host:/var/cache/velocita \
    isaaceindhoven/velocita-proxy
```

## Enabling HTTPS

* Open up port `443`
* Update `VELOCITA_URL` to use `https://`
* Set `VELOCITA_TLS_ENABLED` to `true`
* Provide your X.509 PEM-encoded certificate (or chain) and key file inside the container at `/etc/nginx/server.crt`
  and `/etc/nginx/server.key`

```
docker run -d --name velocita -p 80:8080 -p 443:8443 \
    -e VELOCITA_URL=https://localhost \
    -e VELOCITA_TLS_ENABLED=true \
    -e MIRROR_PACKAGIST_URL=https://repo.packagist.org \
    -e MIRROR_PACKAGIST_TYPE=composer \
    -v /path/to/certificate.crt:/etc/nginx/server.crt \
    -v /path/to/keyfile.pem:/etc/nginx/server.key \
    isaaceindhoven/velocita-proxy
```

HTTP requests will be redirected to HTTPS.

## Enabling basic authentication

* Set `VELOCITA_AUTH_TYPE` to `basic`
* Provide a Nginx-compatible `htpasswd` file inside the container at `/etc/nginx/users.htpasswd`
