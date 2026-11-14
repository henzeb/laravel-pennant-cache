<?php

use Illuminate\Support\Facades\Cache;
use Laravel\Pennant\Feature;

it('uses the configured prefix, index and ttl', function () {
    config()->set('pennant.stores.cache', [
        'driver' => 'cache',
        'store' => 'array',
        'prefix' => 'custom',
        'index' => 'custom-index',
        'ttl' => 60,
    ]);

    Feature::store('cache')->activate('configured-feature');

    expect(Feature::store('cache')->active('configured-feature'))->toBeTrue()
        ->and(Cache::store('array')->has('custom-index'))->toBeTrue();
});

it('rejects an option with the wrong type', function (string $option, mixed $value, string $message) {
    config()->set('pennant.stores.cache', ['driver' => 'cache', 'store' => 'array', $option => $value]);

    expect(fn () => Feature::store('cache')->active('feature'))
        ->toThrow(InvalidArgumentException::class, $message);
})->with([
    'store' => ['store', 1, 'The [store] option of the Pennant cache driver must be a string.'],
    'prefix' => ['prefix', 1, 'The [prefix] option of the Pennant cache driver must be a string.'],
    'index' => ['index', 1, 'The [index] option of the Pennant cache driver must be a string.'],
    'ttl' => ['ttl', '60', 'The [ttl] option of the Pennant cache driver must be an integer.'],
]);
