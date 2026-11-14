<?php

declare(strict_types=1);

namespace Henzeb\Pennant\Cache\Providers;

use Henzeb\Pennant\Cache\Drivers\CacheDriver;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Laravel\Pennant\Feature;
use UnitEnum;

class PennantCacheServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        $storeOption = $this->storeOption(...);
        $stringOption = $this->stringOption(...);
        $integerOption = $this->integerOption(...);

        Feature::extend(
            'cache',
            function (Application $app, array $config) use ($storeOption, $stringOption, $integerOption) {
                $prefix = $stringOption($config, 'prefix') ?? 'pennant';

                return new CacheDriver(
                    $app->make(CacheFactory::class)->store($storeOption($config)),
                    $app->make(Dispatcher::class),
                    $prefix,
                    $stringOption($config, 'index') ?? "{$prefix}:index",
                    $integerOption($config, 'ttl'),
                    [],
                );
            }
        );
    }

    /**
     * @param array<array-key, mixed> $config
     */
    private function storeOption(array $config): string|UnitEnum|null
    {
        $store = $config['store'] ?? null;

        if ($store === null || is_string($store) || $store instanceof UnitEnum) {
            return $store;
        }

        throw new InvalidArgumentException('The [store] option of the Pennant cache driver must be a string.');
    }

    /**
     * @param array<array-key, mixed> $config
     */
    private function stringOption(array $config, string $key): ?string
    {
        $value = $config[$key] ?? null;

        if ($value === null || is_string($value)) {
            return $value;
        }

        throw new InvalidArgumentException("The [{$key}] option of the Pennant cache driver must be a string.");
    }

    /**
     * @param array<array-key, mixed> $config
     */
    private function integerOption(array $config, string $key): ?int
    {
        $value = $config[$key] ?? null;

        if ($value === null || is_int($value)) {
            return $value;
        }

        throw new InvalidArgumentException("The [{$key}] option of the Pennant cache driver must be an integer.");
    }
}
