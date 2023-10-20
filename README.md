# Velocita

[![Docker Image Version (latest semver)](https://img.shields.io/docker/v/isaaceindhoven/velocita-proxy?sort=semver)](https://hub.docker.com/r/isaaceindhoven/velocita-proxy)
[![Docker Pulls](https://img.shields.io/docker/pulls/isaaceindhoven/velocita-proxy)](https://hub.docker.com/r/isaaceindhoven/velocita-proxy)
[![License](https://img.shields.io/github/license/isaaceindhoven/velocita-proxy)](https://github.com/isaaceindhoven/velocita-proxy/blob/master/LICENSE)

Velocita is a caching reverse proxy for Composer repositories and package distribution locations, such as Packagist or GitHub.

* Speeds up downloads for package metadata and dist files
* Serves cached files even if the source location is unreachable
* Can be used as a shared cache by multiple developers, CI, etc.
* No changes required to your project's files!

## Installation

There are two parts to Velocita:

* Velocita Proxy, a caching reverse proxy
* [Composer-velocita](https://github.com/isaaceindhoven/composer-velocita), a plugin which instructs Composer to use a Velocita Proxy instance

### Running Velocita Proxy

Velocita is available as a Docker image. There are two supported ways to run this image:

#### Using `docker compose`

1. Clone this repository:

    ```
    git clone https://github.com/isaaceindhoven/velocita-proxy
    cd velocita-proxy
    ```

2. Copy `.env.dist` to `.env`
3. Edit `.env` and set:

    * `VELOCITA_URL`: the URL (e.g. `https://mydomain.tld`) on which Velocita is hosted
    * `VELOCITA_TLS_ENABLED`: set to `true` to enable HTTPS
    * `VELOCITA_TLS_CERT_FILE`: the path to your X.509 PEM-encoded certificate (or chain) for the domain
    * `VELOCITA_TLS_KEY_FILE`: the path to the private key associated with the certificate

4. Start Velocita:

    ```
    docker compose -f docker-compose.yml -f docker-compose.https.yml up -d
    ```

5. Done!

#### Using docker

Alternatively, you can run the Docker image directly: see [the image's usage instructions](proxy/README.md).

### Using Composer-velocita

[Composer-velocita](https://github.com/isaaceindhoven/composer-velocita) is a Composer plugin that redirects downloads
to your Velocita instance for all repositories it supports.

Run the following commands on the machine where you want to enable Velocita, replacing
`https://url.to.your.velocita.tld/` with the URL of your instance:

```
composer global config allow-plugins.isaac/composer-velocita true
composer global require isaac/composer-velocita
composer velocita:enable https://url.to.your.velocita.tld/
```

And you're all set!

### Configuration

See the [Docker image instructions](proxy/README.md) for environment variables that you can use to configure the
proxy's behavior.

## Performance

Velocita can give you major performance improvements when a package is not present in the local cache. For example,
installing the PHPUnit dependencies from `composer.lock`:

| Configuration       | Duration     | Relative |
| ------------------- |:------------:|:--------:|
| Composer            |  2.2s ± 0.1s |   100%   |
| Composer + Velocita |  0.5s ± 0.1s |    23%   |

Command: `composer install --profile` after `composer require phpunit/phpunit:9.5.19` and clearing both the local cache
and the vendor folder.

Velocita works great together with Symfony Flex:

| Configuration                      | Duration    | Relative |
| ---------------------------------- |:-----------:|:--------:|
| Composer + Symfony Flex            | 4.7s ± 0.7s |   100%   |
| Composer + Symfony Flex + Velocita | 1.6s ± 0.2s |    35%   |

Command: `composer create-project symfony/skeleton:v6.0.99 symfony --profile` after clearing the local cache.

Benchmark setup:

* Velocita is configured with mirrors for Packagist and GitHub Codeload
* PHP version 8.1.4
* Composer version 2.2.10
* Symfony Flex 2.1.6

## Authors

* Jelle Raaijmakers - [jelle@gmta.nl](mailto:jelle@gmta.nl) / [GMTA](https://github.com/GMTA)

## Contributing

Raise an issue or submit a pull request on [GitHub](https://github.com/isaaceindhoven/velocita-proxy).

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.
