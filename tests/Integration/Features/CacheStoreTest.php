<?php

use Laravel\Pennant\Feature;
use Tests\Fixtures\SerializableScope;

beforeEach(function () {
    $this->featureName = 'feature-flag-' . uniqid();
});

it('activates and deactivates a feature without a scope', function () {
    expect(Feature::store('cache')->active($this->featureName))->toBeFalse();

    Feature::store('cache')->activate($this->featureName);

    expect(Feature::store('cache')->active($this->featureName))->toBeTrue();

    Feature::store('cache')->deactivate($this->featureName);

    expect(Feature::store('cache')->active($this->featureName))->toBeFalse();
});

it('stores a value per scope independently', function (string|int|SerializableScope $scope) {
    Feature::store('cache')->for($scope)->activate($this->featureName);

    expect(Feature::store('cache')->for($scope)->active($this->featureName))->toBeTrue()
        ->and(Feature::store('cache')->for('some-other-scope')->active($this->featureName))->toBeFalse();
})->with([
    'string scope' => ['user:1'],
    'integer scope' => [42],
    'object scope' => [new SerializableScope('user-1')],
]);

it('stores a payload value, not just a boolean', function () {
    Feature::store('cache')->for('user:1')->activate($this->featureName, ['plan' => 'gold']);

    expect(Feature::store('cache')->for('user:1')->value($this->featureName))->toBe(['plan' => 'gold']);
});

it('preserves the payload across a plain deactivate/activate cycle', function () {
    Feature::store('cache')->for('user:1')->activate($this->featureName, ['plan' => 'gold']);
    Feature::flushCache();

    Feature::store('cache')->for('user:1')->deactivate($this->featureName);
    Feature::flushCache();

    expect(Feature::store('cache')->for('user:1')->active($this->featureName))->toBeFalse()
        ->and(Feature::store('cache')->for('user:1')->value($this->featureName))->toBeFalse();

    Feature::store('cache')->for('user:1')->activate($this->featureName);
    Feature::flushCache();

    expect(Feature::store('cache')->for('user:1')->active($this->featureName))->toBeTrue()
        ->and(Feature::store('cache')->for('user:1')->value($this->featureName))->toBe(['plan' => 'gold']);
});

it('overwrites the payload when a new one is activated while disabled', function () {
    Feature::store('cache')->for('user:1')->activate($this->featureName, ['plan' => 'gold']);
    Feature::store('cache')->for('user:1')->deactivate($this->featureName);

    Feature::store('cache')->for('user:1')->activate($this->featureName, ['plan' => 'platinum']);

    expect(Feature::store('cache')->for('user:1')->value($this->featureName))->toBe(['plan' => 'platinum']);
});

it('sets a feature for all previously seen scopes at once', function () {
    Feature::store('cache')->for('user:1')->activate($this->featureName);
    Feature::store('cache')->for('user:2')->activate($this->featureName);

    Feature::store('cache')->deactivateForEveryone($this->featureName);

    expect(Feature::store('cache')->for('user:1')->active($this->featureName))->toBeFalse()
        ->and(Feature::store('cache')->for('user:2')->active($this->featureName))->toBeFalse();
});

it('deletes a stored value so it falls back to the resolver again', function () {
    Feature::define($this->featureName, fn () => true);

    Feature::store('cache')->for('user:1')->activate($this->featureName, false);
    expect(Feature::store('cache')->for('user:1')->active($this->featureName))->toBeFalse();

    Feature::store('cache')->for('user:1')->forget($this->featureName);

    expect(Feature::store('cache')->for('user:1')->active($this->featureName))->toBeTrue();
});

it('lists stored features', function () {
    Feature::store('cache')->for('user:1')->activate($this->featureName);

    expect(Feature::store('cache')->stored())->toContain($this->featureName);
});

it('purges a single feature without affecting others', function () {
    $otherFeature = 'feature-flag-' . uniqid();

    Feature::store('cache')->for('user:1')->activate($this->featureName);
    Feature::store('cache')->for('user:1')->activate($otherFeature);

    Feature::store('cache')->purge([$this->featureName]);

    expect(Feature::store('cache')->stored())
        ->not->toContain($this->featureName)
        ->toContain($otherFeature);
});

it('purges everything when no features are given', function () {
    Feature::store('cache')->for('user:1')->activate($this->featureName);

    Feature::store('cache')->purge(null);

    expect(Feature::store('cache')->stored())->toBe([]);
});
