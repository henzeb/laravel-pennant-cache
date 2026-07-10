# Cache driver for Laravel Pennant

[![Build Status](https://github.com/henzeb/laravel-pennant-cache/workflows/tests/badge.svg)](https://github.com/henzeb/laravel-pennant-cache/actions)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/henzeb/laravel-pennant-cache.svg?style=flat-square)](https://packagist.org/packages/henzeb/laravel-pennant-cache)
[![Total Downloads](https://img.shields.io/packagist/dt/henzeb/laravel-pennant-cache.svg?style=flat-square)](https://packagist.org/packages/henzeb/laravel-pennant-cache)
[![License](https://img.shields.io/packagist/l/henzeb/laravel-pennant-cache)](https://packagist.org/packages/henzeb/laravel-pennant-cache)

This package adds a `cache` driver for [Laravel Pennant](https://laravel.com/docs/master/pennant) that stores
feature flag values using any Laravel cache store, e.g. `redis`, `memcached`, `dynamodb` or `array`.

## Why

Pennant ships with `array` and `database` drivers out of the box, and both are good at what they're meant for:
`array` is great for testing since it never persists beyond a single request, and `database` is a solid production
option, storing every feature value in its own table. This driver is just another option for when you'd rather
reuse a cache store you already have running — `store` can point at any store your `config/cache.php` defines,
including its own `database` cache store, so this doesn't have to mean giving up a database backend either.

## Installation

```bash
composer require henzeb/laravel-pennant-cache
```

Add a `cache` entry to the `stores` section of `config/pennant.php`, pointing `store` at whichever cache store
(as configured in `config/cache.php`) should hold the feature values:

```php
'stores' => [
    'cache' => [
        'driver' => 'cache',
        'store' => 'redis',
    ],
],
```

> **Tip:** consider pointing `store` at a cache store dedicated to Pennant rather than reusing your app's default
> one, so feature flags aren't flushed by a stray `Cache::flush()` or evicted to make room for unrelated cache
> entries. For example, define a separate `redis` store in `config/cache.php` using its own connection/database:
>
> ```php
> 'stores' => [
>     'pennant' => [
>         'driver' => 'redis',
>         'connection' => 'pennant',
>     ],
> ],
> ```
>
> and point this driver at it: `'store' => 'pennant'`.

## Configuration

| Key      | Default          | Description                                                                                     |
|----------|-------------------|---------------------------------------------------------------------------------------------------|
| `store`  | app's default cache store | The name of the cache store (from `config/cache.php`) to read and write feature values from. |
| `prefix` | `pennant`         | Prefix used for every cache key this driver writes, to avoid colliding with unrelated cache keys.  |
| `index`  | `{prefix}:index`  | Cache key used to keep track of which feature/scope pairs have been stored, for `stored()` and `purge()`. Set this explicitly if you run more than one `cache` store against the same underlying cache store, so their indexes don't collide. |
| `ttl`    | `null` (forever)  | Seconds until a stored feature value expires. Left at `null`, values are stored with `forever()` and only disappear when deleted, purged, or evicted by the cache store itself (e.g. Redis under memory pressure). |

```php
'stores' => [
    'cache' => [
        'driver' => 'cache',
        'store' => 'redis',
        'prefix' => 'my-app-features',
        'ttl' => 3600,
    ],
],
```

## Activating/deactivating without losing the payload

Usually, when you have a feature flag with a payload, deactivating it means losing that payload — activating it
again means setting the payload again from scratch. This driver treats a plain `true`/`false` (what
`Feature::activate()` and `::deactivate()` pass along) purely as an on/off toggle, and keeps the previously stored
payload untouched, so a feature can be switched off and back on without losing its data:

```php
Feature::store('cache')->for($user)->activate('discount', ['percentage' => 10]);

Feature::store('cache')->for($user)->deactivate('discount');
Feature::store('cache')->for($user)->value('discount'); // false

Feature::store('cache')->for($user)->activate('discount');
Feature::store('cache')->for($user)->value('discount'); // ['percentage' => 10] again
```

Passing an actual payload (anything other than a literal `true`/`false`) always overwrites the stored value,
regardless of whether the feature was active or not. If a feature is activated for the very first time with no
payload of its own, and it has a `Feature::define()` resolver whose default is itself a payload (not a plain
boolean), that default is used instead of an empty value.

> **Note:** Pennant's own `Decorator` keeps a local, per-request memoization of every value it reads or writes,
> and assumes the value it just wrote is exactly the value it will read back. Because this driver can return a
> different, previously stored payload than what was just passed to `activate()`/`deactivate()`, that assumption
> doesn't hold within the same request. In practice this rarely matters — Pennant flushes that local cache at the
> start of every request/queued job/Octane tick — but if you activate/deactivate and immediately read the value
> back within the same request (e.g. in a test), call `Feature::flushCache()` in between to see the driver's real
> state rather than the Decorator's stale local copy.

## Testing this package

```bash
composer test
```

## Security

If you discover any security related issues, please email henzeberkheij@gmail.com instead of using the issue tracker.

## Credits

- [Henze Berkheij](https://github.com/henzeb)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
