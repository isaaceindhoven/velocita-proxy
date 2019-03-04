# Velocita

Velocita is a caching proxy for Composer repositories such as Packagist and distribution locations like GitHub.

* Speeds up downloads for package metadata and dist files
* Serves cached files if the source location is unreachable or experiencing problems
* Can be used as a shared cache by multiple developers
* No changes required to your project's files

## Performance

Velocita can give you major performance improvements when a package is not present in the local cache. For example,
installing the PHPUnit dependencies from `composer.lock`:

| Configuration       | Time           | Relative |
| ------------------- |:--------------:|:--------:|
| Composer            | 25.59s ± 1.14s |   100%   |
| Composer + Velocita | 1.14s ± 0.05s  |    4%    |

Benchmark: `composer install --profile` after `composer require phpunit/phpunit:8.0.4` and clearing both the local cache
and the vendor folder.

Symfony Flex's parallel prefetcher can also benefit from Velocita:

| Configuration           | Time           | Relative |
| ----------------------- |:--------------:|:--------:|
| Symfony Flex            | 13.13s ± 0.17s |   100%   |
| Symfony Flex + Velocita | 10.59s ± 0.20s |    81%   |

Benchmark: `composer create-project symfony/skeleton symfony --profile` after clearing the local cache.

## Installation

### Velocita proxy

WIP

### Composer-velocita

[Composer-velocita](https://github.com/isaaceindhoven/composer-velocita) is a Composer plugin that redirects downloads
to your Velocita instance for all repositories it supports, with no changes required in `composer.json`.

Run the following two commands on the machine where you want to enable Velocita, replacing
`https://url.to.your.velocita.tld/` with the location of your instance:

```
composer global require isaac/composer-velocita
composer velocita:enable https://url.to.your.velocita.tld/
```

You're all set!

## Authors

* Jelle Raaijmakers - [jelle.raaijmakers@isaac.nl](mailto:jelle.raaijmakers@isaac.nl) / [GMTA](https://github.com/GMTA)

## License

This project is licensed under the MIT License - see the
[LICENSE](https://github.com/isaaceindhoven/velocita/blob/master/LICENSE) file for details.
