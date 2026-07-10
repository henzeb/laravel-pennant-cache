<?php

use Henzeb\Pennant\Cache\Drivers\CacheDriver;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Laravel\Pennant\Events\UnknownFeatureResolved;

if (function_exists('covers')) {
    covers(CacheDriver::class);
}

afterEach(function () {
    Mockery::close();
});

function makeCacheDriver(
    Repository $cache,
    ?Dispatcher $events = null,
    array $resolvers = [],
    ?int $ttl = null,
): CacheDriver {
    return new CacheDriver(
        $cache,
        $events ?? Mockery::mock(Dispatcher::class),
        prefix: 'pennant',
        indexKey: 'pennant:index',
        ttl: $ttl,
        featureStateResolvers: $resolvers,
    );
}

it('treats a stored value with no payload as a plain boolean true', function () {
    $cache = Mockery::mock(Repository::class);
    $cache->shouldReceive('has')->once()->with('pennant:my-feature:__laravel_null')->andReturnTrue();
    $cache->shouldReceive('get')->once()->with('pennant:my-feature:__laravel_null')
        ->andReturn(['enabled' => true, 'value' => null]);

    $driver = makeCacheDriver($cache);

    expect($driver->get('my-feature', null))->toBeTrue();
});

it('returns the stored payload when enabled', function () {
    $cache = Mockery::mock(Repository::class);
    $cache->shouldReceive('has')->once()->with('pennant:my-feature:__laravel_null')->andReturnTrue();
    $cache->shouldReceive('get')->once()->with('pennant:my-feature:__laravel_null')
        ->andReturn(['enabled' => true, 'value' => ['plan' => 'gold']]);

    $driver = makeCacheDriver($cache);

    expect($driver->get('my-feature', null))->toBe(['plan' => 'gold']);
});

it('returns false when disabled, even if a payload is still stored', function () {
    $cache = Mockery::mock(Repository::class);
    $cache->shouldReceive('has')->once()->with('pennant:my-feature:__laravel_null')->andReturnTrue();
    $cache->shouldReceive('get')->once()->with('pennant:my-feature:__laravel_null')
        ->andReturn(['enabled' => false, 'value' => ['plan' => 'gold']]);

    $driver = makeCacheDriver($cache);

    expect($driver->get('my-feature', null))->toBeFalse();
});

it('resolves, stores and returns the value for a defined feature that is not cached yet', function () {
    $cache = Mockery::mock(Repository::class);
    $cache->shouldReceive('has')->once()->andReturnFalse();
    $cache->shouldReceive('forever')->once()->with('pennant:my-feature:__laravel_null', ['enabled' => true, 'value' => null]);
    $cache->shouldReceive('get')->once()->with('pennant:index')->andReturnNull();
    $cache->shouldReceive('forever')->once()->with('pennant:index', ['my-feature']);
    $cache->shouldReceive('get')->once()->with('pennant:index:my-feature')->andReturnNull();
    $cache->shouldReceive('forever')->once()->with('pennant:index:my-feature', ['__laravel_null']);

    $driver = makeCacheDriver($cache, resolvers: ['my-feature' => fn () => true]);

    expect($driver->get('my-feature', null))->toBeTrue();
});

it('dispatches an event and returns false for an undefined feature', function () {
    $cache = Mockery::mock(Repository::class);
    $cache->shouldReceive('has')->once()->andReturnFalse();

    $events = Mockery::mock(Dispatcher::class);
    $events->shouldReceive('dispatch')->once()->with(Mockery::type(UnknownFeatureResolved::class));

    $driver = makeCacheDriver($cache, $events);

    expect($driver->get('my-feature', null))->toBeFalse();
});

it('stores a plain true as enabled with no payload on a fresh key', function () {
    $cache = Mockery::mock(Repository::class);
    $cache->shouldReceive('get')->once()->with('pennant:my-feature:my-scope')->andReturnNull();
    $cache->shouldReceive('forever')->once()->with('pennant:my-feature:my-scope', ['enabled' => true, 'value' => null]);
    $cache->shouldReceive('get')->once()->with('pennant:index')->andReturnNull();
    $cache->shouldReceive('forever')->once()->with('pennant:index', ['my-feature']);
    $cache->shouldReceive('get')->once()->with('pennant:index:my-feature')->andReturnNull();
    $cache->shouldReceive('forever')->once()->with('pennant:index:my-feature', ['my-scope']);

    $driver = makeCacheDriver($cache);

    $driver->set('my-feature', 'my-scope', true);
});

it('stores a plain false as disabled with no payload on a fresh key', function () {
    $cache = Mockery::mock(Repository::class);
    $cache->shouldReceive('get')->once()->with('pennant:my-feature:my-scope')->andReturnNull();
    $cache->shouldReceive('forever')->once()->with('pennant:my-feature:my-scope', ['enabled' => false, 'value' => null]);
    $cache->shouldReceive('get')->once()->with('pennant:index')->andReturnNull();
    $cache->shouldReceive('forever')->once()->with('pennant:index', ['my-feature']);
    $cache->shouldReceive('get')->once()->with('pennant:index:my-feature')->andReturnNull();
    $cache->shouldReceive('forever')->once()->with('pennant:index:my-feature', ['my-scope']);

    $driver = makeCacheDriver($cache);

    $driver->set('my-feature', 'my-scope', false);
});

it('stores a real payload as enabled with that payload', function () {
    $cache = Mockery::mock(Repository::class);
    $cache->shouldReceive('get')->once()->with('pennant:my-feature:my-scope')->andReturnNull();
    $cache->shouldReceive('forever')->once()->with('pennant:my-feature:my-scope', ['enabled' => true, 'value' => ['plan' => 'gold']]);
    $cache->shouldReceive('get')->once()->with('pennant:index')->andReturnNull();
    $cache->shouldReceive('forever')->once()->with('pennant:index', ['my-feature']);
    $cache->shouldReceive('get')->once()->with('pennant:index:my-feature')->andReturnNull();
    $cache->shouldReceive('forever')->once()->with('pennant:index:my-feature', ['my-scope']);

    $driver = makeCacheDriver($cache);

    $driver->set('my-feature', 'my-scope', ['plan' => 'gold']);
});

it('uses the defined resolver default as the payload for a fresh key toggled by set()', function () {
    $cache = Mockery::mock(Repository::class);
    $cache->shouldReceive('get')->once()->with('pennant:my-feature:my-scope')->andReturnNull();
    $cache->shouldReceive('forever')->once()->with('pennant:my-feature:my-scope', ['enabled' => true, 'value' => ['plan' => 'basic']]);
    $cache->shouldReceive('get')->once()->with('pennant:index')->andReturnNull();
    $cache->shouldReceive('forever')->once()->with('pennant:index', ['my-feature']);
    $cache->shouldReceive('get')->once()->with('pennant:index:my-feature')->andReturnNull();
    $cache->shouldReceive('forever')->once()->with('pennant:index:my-feature', ['my-scope']);

    $driver = makeCacheDriver($cache, resolvers: ['my-feature' => fn () => ['plan' => 'basic']]);

    $driver->set('my-feature', 'my-scope', true);
});

it('ignores a boolean resolver default as a payload for a fresh key toggled by set()', function () {
    $cache = Mockery::mock(Repository::class);
    $cache->shouldReceive('get')->once()->with('pennant:my-feature:my-scope')->andReturnNull();
    $cache->shouldReceive('forever')->once()->with('pennant:my-feature:my-scope', ['enabled' => true, 'value' => null]);
    $cache->shouldReceive('get')->once()->with('pennant:index')->andReturnNull();
    $cache->shouldReceive('forever')->once()->with('pennant:index', ['my-feature']);
    $cache->shouldReceive('get')->once()->with('pennant:index:my-feature')->andReturnNull();
    $cache->shouldReceive('forever')->once()->with('pennant:index:my-feature', ['my-scope']);

    $driver = makeCacheDriver($cache, resolvers: ['my-feature' => fn () => true]);

    $driver->set('my-feature', 'my-scope', true);
});

it('preserves the existing payload when toggling a feature off', function () {
    $cache = Mockery::mock(Repository::class);
    $cache->shouldReceive('get')->once()->with('pennant:my-feature:my-scope')
        ->andReturn(['enabled' => true, 'value' => ['plan' => 'gold']]);
    $cache->shouldReceive('forever')->once()->with('pennant:my-feature:my-scope', ['enabled' => false, 'value' => ['plan' => 'gold']]);
    $cache->shouldReceive('get')->once()->with('pennant:index')->andReturn(['my-feature']);
    $cache->shouldReceive('get')->once()->with('pennant:index:my-feature')->andReturn(['my-scope']);

    $driver = makeCacheDriver($cache);

    $driver->set('my-feature', 'my-scope', false);
});

it('preserves the existing payload when reactivating a feature', function () {
    $cache = Mockery::mock(Repository::class);
    $cache->shouldReceive('get')->once()->with('pennant:my-feature:my-scope')
        ->andReturn(['enabled' => false, 'value' => ['plan' => 'gold']]);
    $cache->shouldReceive('forever')->once()->with('pennant:my-feature:my-scope', ['enabled' => true, 'value' => ['plan' => 'gold']]);
    $cache->shouldReceive('get')->once()->with('pennant:index')->andReturn(['my-feature']);
    $cache->shouldReceive('get')->once()->with('pennant:index:my-feature')->andReturn(['my-scope']);

    $driver = makeCacheDriver($cache);

    $driver->set('my-feature', 'my-scope', true);
});

it('overwrites a previous payload when a new one is set, regardless of enabled state', function () {
    $cache = Mockery::mock(Repository::class);
    $cache->shouldReceive('get')->once()->with('pennant:my-feature:my-scope')
        ->andReturn(['enabled' => false, 'value' => ['plan' => 'gold']]);
    $cache->shouldReceive('forever')->once()->with('pennant:my-feature:my-scope', ['enabled' => true, 'value' => ['plan' => 'platinum']]);
    $cache->shouldReceive('get')->once()->with('pennant:index')->andReturn(['my-feature']);
    $cache->shouldReceive('get')->once()->with('pennant:index:my-feature')->andReturn(['my-scope']);

    $driver = makeCacheDriver($cache);

    $driver->set('my-feature', 'my-scope', ['plan' => 'platinum']);
});

it('stores a value with a ttl when one is configured', function () {
    $cache = Mockery::mock(Repository::class);
    $cache->shouldReceive('get')->once()->with('pennant:my-feature:my-scope')->andReturnNull();
    $cache->shouldReceive('put')->once()->with('pennant:my-feature:my-scope', ['enabled' => true, 'value' => null], 60);
    $cache->shouldReceive('get')->once()->with('pennant:index')->andReturnNull();
    $cache->shouldReceive('put')->once()->with('pennant:index', ['my-feature'], 60);
    $cache->shouldReceive('get')->once()->with('pennant:index:my-feature')->andReturnNull();
    $cache->shouldReceive('put')->once()->with('pennant:index:my-feature', ['my-scope'], 60);

    $driver = makeCacheDriver($cache, ttl: 60);

    $driver->set('my-feature', 'my-scope', true);
});

it('does not duplicate index entries when the same feature and scope are set again', function () {
    $cache = Mockery::mock(Repository::class);
    $cache->shouldReceive('get')->twice()->with('pennant:my-feature:my-scope')->andReturnNull();
    $cache->shouldReceive('forever')->twice()->with('pennant:my-feature:my-scope', ['enabled' => true, 'value' => null]);
    $cache->shouldReceive('get')->twice()->with('pennant:index')->andReturn(null, ['my-feature']);
    $cache->shouldReceive('forever')->once()->with('pennant:index', ['my-feature']);
    $cache->shouldReceive('get')->twice()->with('pennant:index:my-feature')->andReturn(null, ['my-scope']);
    $cache->shouldReceive('forever')->once()->with('pennant:index:my-feature', ['my-scope']);

    $driver = makeCacheDriver($cache);

    $driver->set('my-feature', 'my-scope', true);
    $driver->set('my-feature', 'my-scope', true);
});

it('removes the cache entry and drops the feature from the index once its last scope is deleted', function () {
    $cache = Mockery::mock(Repository::class);
    $cache->shouldReceive('forget')->once()->with('pennant:my-feature:my-scope');
    $cache->shouldReceive('get')->once()->with('pennant:index:my-feature')->andReturn(['my-scope']);
    $cache->shouldReceive('forget')->once()->with('pennant:index:my-feature');
    $cache->shouldReceive('get')->once()->with('pennant:index')->andReturn(['my-feature', 'other-feature']);
    $cache->shouldReceive('forever')->once()->with('pennant:index', ['other-feature']);

    $driver = makeCacheDriver($cache);

    $driver->delete('my-feature', 'my-scope');
});

it('keeps the feature in the index when it still has other scopes after a delete', function () {
    $cache = Mockery::mock(Repository::class);
    $cache->shouldReceive('forget')->once()->with('pennant:my-feature:scope-a');
    $cache->shouldReceive('get')->once()->with('pennant:index:my-feature')->andReturn(['scope-a', 'scope-b']);
    $cache->shouldReceive('forever')->once()->with('pennant:index:my-feature', ['scope-b']);
    $cache->shouldNotReceive('forget')->with('pennant:index:my-feature');

    $driver = makeCacheDriver($cache);

    $driver->delete('my-feature', 'scope-a');
});

it('lists the feature names present in the index', function () {
    $cache = Mockery::mock(Repository::class);
    $cache->shouldReceive('get')->once()->with('pennant:index')->andReturn(['my-feature', 'other-feature']);

    $driver = makeCacheDriver($cache);

    expect($driver->stored())->toBe(['my-feature', 'other-feature']);
});

it('returns an empty array for stored() when nothing has been set', function () {
    $cache = Mockery::mock(Repository::class);
    $cache->shouldReceive('get')->once()->with('pennant:index')->andReturnNull();

    $driver = makeCacheDriver($cache);

    expect($driver->stored())->toBe([]);
});

it('forgets every cache entry and clears the index when purging everything', function () {
    $cache = Mockery::mock(Repository::class);
    $cache->shouldReceive('get')->once()->with('pennant:index')->andReturn(['my-feature', 'other-feature']);
    $cache->shouldReceive('get')->once()->with('pennant:index:my-feature')->andReturn(['a']);
    $cache->shouldReceive('get')->once()->with('pennant:index:other-feature')->andReturn(['b']);
    $cache->shouldReceive('forget')->once()->with('pennant:my-feature:a');
    $cache->shouldReceive('forget')->once()->with('pennant:other-feature:b');
    $cache->shouldReceive('forget')->once()->with('pennant:index:my-feature');
    $cache->shouldReceive('forget')->once()->with('pennant:index:other-feature');
    $cache->shouldReceive('forget')->once()->with('pennant:index');

    $driver = makeCacheDriver($cache);

    $driver->purge(null);
});

it('only forgets entries for the given features when purging a subset', function () {
    $cache = Mockery::mock(Repository::class);
    $cache->shouldReceive('get')->once()->with('pennant:index:my-feature')->andReturn(['a']);
    $cache->shouldReceive('forget')->once()->with('pennant:my-feature:a');
    $cache->shouldReceive('forget')->once()->with('pennant:index:my-feature');
    $cache->shouldNotReceive('forget')->with('pennant:other-feature:b');
    $cache->shouldNotReceive('forget')->with('pennant:index:other-feature');
    $cache->shouldReceive('get')->once()->with('pennant:index')->andReturn(['my-feature', 'other-feature']);
    $cache->shouldReceive('forever')->once()->with('pennant:index', ['other-feature']);

    $driver = makeCacheDriver($cache);

    $driver->purge(['my-feature']);
});

it('delegates getAll() to get() per feature and scope', function () {
    $cache = Mockery::mock(Repository::class);
    $cache->shouldReceive('has')->times(3)->andReturnTrue();
    $cache->shouldReceive('get')->once()->with('pennant:my-feature:scope-a')
        ->andReturn(['enabled' => true, 'value' => null]);
    $cache->shouldReceive('get')->once()->with('pennant:my-feature:scope-b')
        ->andReturn(['enabled' => false, 'value' => null]);
    $cache->shouldReceive('get')->once()->with('pennant:other-feature:__laravel_null')
        ->andReturn(['enabled' => true, 'value' => null]);

    $driver = makeCacheDriver($cache);

    expect($driver->getAll([
        'my-feature' => ['scope-a', 'scope-b'],
        'other-feature' => [null],
    ]))->toBe([
        'my-feature' => [true, false],
        'other-feature' => [true],
    ]);
});

it('delegates setAll() to set() per entry', function () {
    $cache = Mockery::mock(Repository::class);
    $cache->shouldReceive('get')->once()->with('pennant:my-feature:my-scope')->andReturnNull();
    $cache->shouldReceive('forever')->once()->with('pennant:my-feature:my-scope', ['enabled' => true, 'value' => null]);
    $cache->shouldReceive('get')->once()->with('pennant:other-feature:__laravel_null')->andReturnNull();
    $cache->shouldReceive('forever')->once()->with('pennant:other-feature:__laravel_null', ['enabled' => false, 'value' => null]);
    $cache->shouldReceive('get')->twice()->with('pennant:index')->andReturn(null, ['my-feature']);
    $cache->shouldReceive('forever')->once()->with('pennant:index', ['my-feature']);
    $cache->shouldReceive('forever')->once()->with('pennant:index', ['my-feature', 'other-feature']);
    $cache->shouldReceive('get')->once()->with('pennant:index:my-feature')->andReturnNull();
    $cache->shouldReceive('forever')->once()->with('pennant:index:my-feature', ['my-scope']);
    $cache->shouldReceive('get')->once()->with('pennant:index:other-feature')->andReturnNull();
    $cache->shouldReceive('forever')->once()->with('pennant:index:other-feature', ['__laravel_null']);

    $driver = makeCacheDriver($cache);

    $driver->setAll([
        ['feature' => 'my-feature', 'scope' => 'my-scope', 'value' => true],
        ['feature' => 'other-feature', 'scope' => null, 'value' => false],
    ]);
});

it('updates every indexed scope for a feature via setForAllScopes(), preserving each scope\'s payload', function () {
    $cache = Mockery::mock(Repository::class);
    $cache->shouldReceive('get')->once()->with('pennant:index:my-feature')->andReturn(['a', 'b']);
    $cache->shouldReceive('get')->once()->with('pennant:my-feature:a')
        ->andReturn(['enabled' => true, 'value' => ['plan' => 'gold']]);
    $cache->shouldReceive('forever')->once()->with('pennant:my-feature:a', ['enabled' => false, 'value' => ['plan' => 'gold']]);
    $cache->shouldReceive('get')->once()->with('pennant:my-feature:b')->andReturnNull();
    $cache->shouldReceive('forever')->once()->with('pennant:my-feature:b', ['enabled' => false, 'value' => null]);

    $driver = makeCacheDriver($cache);

    $driver->setForAllScopes('my-feature', false);
});

it('defines and lists defined features', function () {
    $driver = makeCacheDriver(Mockery::mock(Repository::class), resolvers: ['already-defined' => fn () => true]);

    $driver->define('new-feature', fn () => false);

    expect($driver->defined())->toBe(['already-defined', 'new-feature']);
});
