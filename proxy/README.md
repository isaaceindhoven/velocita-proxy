# Velocita Proxy Docker image

[Velocita](https://github.com/isaaceindhoven/velocita-proxy) acts as a caching reverse proxy to Composer repositories.

Combined with [composer-velocita](https://github.com/isaaceindhoven/composer-velocita), it can tremendously increase the
performance of a `composer install` where a local cache is not yet available and makes sure your dependencies are
accessible even when the source location is experiencing issues.

## Running the proxy

Start a proxy for Packagist and GitHub, listening on port `80`:

```
docker run -d --name velocita -p 80:80 \
    -e MIRROR_PACKAGIST_URL=https://repo.packagist.org \
    -e MIRROR_PACKAGIST_TYPE=composer \
    -e MIRROR_GITHUB_CODELOAD_URL=https://codeload.github.com \
    isaaceindhoven/velocita-proxy
```

Go ahead and visit `http://localhost/mirror/packagist/p/packages.json` - this should now give you the Packagist index!

## Velocita configuration

Velocita configures itself with the environment variables you pass.

| Environment variable   | Value    | Default            | Description              |
| ---------------------- | -------- | ------------------ | ------------------------ |
| `VELOCITA_URL`         | URL      | `http://localhost` | Used for redirects.      |
| `VELOCITA_TLS_ENABLED` | Boolean  | `false`            | Whether to enable HTTPS. |

## Adding mirrors

A mirror is added if you pass a variable of the form `MIRROR_{name}_URL`, and is configured with additional
`MIRROR_{name}_*` variables.

When you add a Composer repository, make sure you set `MIRROR_{name}_TYPE` to `composer` so the correct cache
invalidation is applied.

For example, this will proxy two different Composer repositories:

```
docker run -d --name velocita -p 80:80 \
    -e MIRROR_WPACKAGIST_URL=https://wpackagist.org \
    -e MIRROR_WPACKAGIST_TYPE=composer \
    -e MIRROR_FIREGENTO_URL=https://packages.firegento.com \
    -e MIRROR_FIREGENTO_TYPE=composer \
    isaaceindhoven/velocita-proxy
```

## Configuring mirrors

For every mirror, the following configuration options are available:

| Environment variable          | Value                    | Default  | Description                              |
| ----------------------------- | ------------------------ | -------- | ---------------------------------------- |
| `MIRROR_{name}_URL`           | URL                      | _(nil)_  | The URL of the upstream server to proxy. |
| `MIRROR_{name}_TYPE`          | `simple`, `composer`     | `simple` | The mirror type.                         |
| `MIRROR_{name}_CACHE_EXPIRY`  | Time (e.g. `5d`, `10m`)  | `3650d`  | Time after which cached items expire.    |
| `MIRROR_{name}_CACHE_SIZE`    | Size (e.g. `100m`, `2g`) | `1g`     | Maximum size of this mirror's cache.     |
| `MIRROR_{name}_AUTH_TYPE`     | `basic`                  | _(nil)_  | Type of upstream authentication.         |
| `MIRROR_{name}_AUTH_USERNAME` | Username                 | _(nil)_  | Username for basic authentication.       |
| `MIRROR_{name}_AUTH_PASSWORD` | Password                 | _(nil)_  | Password for basic authentication.       |

## Storage

Inside the container, all cache files are stored in a volume mounted on `/var/cache/velocita`. Use `-v` to mount this
volume somewhere on your host's filesystem:

```
docker run -d --name velocita -p 80:80 \
    -e MIRROR_PACKAGIST_URL=https://repo.packagist.org \
    -e MIRROR_PACKAGIST_TYPE=composer \
    -v /path/on/host:/var/cache/velocita \
    isaaceindhoven/velocita-proxy
```

## Enabling HTTPS

* Open up port `443`
* Update `VELOCITA_URL` to use `https://`
* Set `VELOCITA_TLS_ENABLED` to `true`
* Mount your X.509 PEM-encoded certificate (or chain) and key file inside the container at `/etc/nginx/server.crt` and
  `/etc/nginx/server.key`

```
docker run -d --name velocita -p 80:80 -p 443:443 \
    -e VELOCITA_URL=https://localhost \
    -e VELOCITA_TLS_ENABLED=true \
    -e MIRROR_PACKAGIST_URL=https://repo.packagist.org \
    -e MIRROR_PACKAGIST_TYPE=composer \
    -v /path/to/certificate.crt:/etc/nginx/server.crt \
    -v /path/to/keyfile.pem:/etc/nginx/server.key \
    isaaceindhoven/velocita-proxy
```

HTTP requests will be redirected to HTTPS.
