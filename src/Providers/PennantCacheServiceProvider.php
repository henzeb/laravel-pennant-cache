<?php

declare(strict_types=1);

namespace Henzeb\Pennant\Cache\Providers;

use Henzeb\Pennant\Cache\Drivers\CacheDriver;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Laravel\Pennant\Feature;

class PennantCacheServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        Feature::extend('cache', function (Application $app, array $config) {
            $prefix = $config['prefix'] ?? 'pennant';

            return new CacheDriver(
                $app->make(CacheFactory::class)->store($config['store'] ?? null),
                $app->make(Dispatcher::class),
                $prefix,
                $config['index'] ?? "{$prefix}:index",
                $config['ttl'] ?? null,
                [],
            );
        });
    }
}
