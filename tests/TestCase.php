<?php

namespace Tests;

use Henzeb\Pennant\Cache\Providers\PennantCacheServiceProvider;
use Laravel\Pennant\Feature;
use Laravel\Pennant\PennantServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            PennantServiceProvider::class,
            PennantCacheServiceProvider::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        Feature::resolveScopeUsing(fn () => null);
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('pennant.stores.cache', ['driver' => 'cache', 'store' => 'array']);
        $app['config']->set('pennant.default', 'cache');
    }
}
