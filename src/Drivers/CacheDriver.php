<?php

declare(strict_types=1);

namespace Henzeb\Pennant\Cache\Drivers;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Laravel\Pennant\Contracts\CanListStoredFeatures;
use Laravel\Pennant\Contracts\CanSetManyFeaturesForScopes;
use Laravel\Pennant\Contracts\Driver;
use Laravel\Pennant\Events\UnknownFeatureResolved;
use Laravel\Pennant\Feature;
use stdClass;

class CacheDriver implements CanListStoredFeatures, CanSetManyFeaturesForScopes, Driver
{
    private readonly stdClass $unknownFeatureValue;

    /**
     * @param  array<string, (callable(mixed $scope): mixed)>  $featureStateResolvers
     */
    public function __construct(
        private readonly Repository $cache,
        private readonly Dispatcher $events,
        private readonly string $prefix,
        private readonly string $indexKey,
        private readonly ?int $ttl,
        private array $featureStateResolvers,
    ) {
        $this->unknownFeatureValue = new stdClass();
    }

    public function define(string $feature, callable $resolver): void
    {
        $this->featureStateResolvers[$feature] = $resolver;
    }

    public function defined(): array
    {
        return array_keys($this->featureStateResolvers);
    }

    public function stored(): array
    {
        return $this->features();
    }

    public function getAll(array $features): array
    {
        $results = [];

        foreach ($features as $feature => $scopes) {
            $results[$feature] = array_map(
                fn(mixed $scope) => $this->get($feature, $scope),
                $scopes
            );
        }

        return $results;
    }

    public function get(string $feature, mixed $scope): mixed
    {
        $key = $this->key($feature, $scope);

        if ($this->cache->has($key)) {
            /** @var array{enabled: bool, value: mixed} $stored */
            $stored = $this->cache->get($key);

            return $stored['enabled'] ? ($stored['value'] ?? true) : false;
        }

        $value = $this->resolveValue($feature, $scope);

        if ($value === $this->unknownFeatureValue) {
            return false;
        }

        $this->put($key, $this->wrapValue(null, $value));
        $this->addToIndex($feature, Feature::serializeScope($scope));

        return $value;
    }

    protected function resolveValue(string $feature, mixed $scope): mixed
    {
        if (!array_key_exists($feature, $this->featureStateResolvers)) {
            $this->events->dispatch(new UnknownFeatureResolved($feature, $scope));

            return $this->unknownFeatureValue;
        }

        return $this->featureStateResolvers[$feature]($scope);
    }

    public function set(string $feature, mixed $scope, mixed $value): void
    {
        $key = $this->key($feature, $scope);

        $this->put($key, $this->wrapValue($this->existingOrDefaultValue($key, $feature, $scope), $value));

        $this->addToIndex($feature, Feature::serializeScope($scope));
    }

    public function setAll(array $features): void
    {
        foreach ($features as $feature) {
            $this->set($feature['feature'], $feature['scope'], $feature['value']);
        }
    }

    public function setForAllScopes(string $feature, mixed $value): void
    {
        foreach ($this->scopes($feature) as $scopeKey) {
            $key = $this->keyForScope($feature, $scopeKey);

            $this->put($key, $this->wrapValue($this->existingValue($key), $value));
        }
    }

    /**
     * @return array{enabled: bool, value: mixed}
     */
    protected function wrapValue(mixed $existingValue, mixed $value): array
    {
        return match (true) {
            $value === true => ['enabled' => true, 'value' => $existingValue],
            $value === false => ['enabled' => false, 'value' => $existingValue],
            default => ['enabled' => true, 'value' => $value],
        };
    }

    protected function existingValue(string $key): mixed
    {
        $stored = $this->cache->get($key);

        return is_array($stored) ? ($stored['value'] ?? null) : null;
    }

    protected function existingOrDefaultValue(string $key, string $feature, mixed $scope): mixed
    {
        $stored = $this->cache->get($key);

        if (is_array($stored)) {
            return $stored['value'] ?? null;
        }

        if (!array_key_exists($feature, $this->featureStateResolvers)) {
            return null;
        }

        $default = $this->featureStateResolvers[$feature]($scope);

        return is_bool($default) ? null : $default;
    }

    public function delete(string $feature, mixed $scope): void
    {
        $this->cache->forget($this->key($feature, $scope));

        $this->removeFromIndex($feature, Feature::serializeScope($scope));
    }

    /**
     * @param  array<string>|null  $features
     */
    public function purge(?array $features): void
    {
        $toPurge = $features ?? $this->features();

        foreach ($toPurge as $feature) {
            foreach ($this->scopes($feature) as $scopeKey) {
                $this->cache->forget($this->keyForScope($feature, $scopeKey));
            }

            $this->cache->forget($this->scopesIndexKey($feature));
        }

        if ($features === null) {
            $this->cache->forget($this->indexKey);

            return;
        }

        $this->putFeatures(array_values(array_diff($this->features(), $features)));
    }

    /**
     * @return list<string>
     */
    protected function features(): array
    {
        /** @var list<string> $features */
        $features = $this->cache->get($this->indexKey) ?? [];

        return $features;
    }

    /**
     * @param  list<string>  $features
     */
    protected function putFeatures(array $features): void
    {
        $this->put($this->indexKey, $features);
    }

    /**
     * @return list<string>
     */
    protected function scopes(string $feature): array
    {
        /** @var list<string> $scopes */
        $scopes = $this->cache->get($this->scopesIndexKey($feature)) ?? [];

        return $scopes;
    }

    /**
     * @param  list<string>  $scopes
     */
    protected function putScopes(string $feature, array $scopes): void
    {
        $this->put($this->scopesIndexKey($feature), $scopes);
    }

    protected function addToIndex(string $feature, string $scopeKey): void
    {
        $features = $this->features();

        if (!in_array($feature, $features, true)) {
            $features[] = $feature;

            $this->putFeatures($features);
        }

        $scopes = $this->scopes($feature);

        if (!in_array($scopeKey, $scopes, true)) {
            $scopes[] = $scopeKey;

            $this->putScopes($feature, $scopes);
        }
    }

    protected function removeFromIndex(string $feature, string $scopeKey): void
    {
        $scopes = array_values(array_diff($this->scopes($feature), [$scopeKey]));

        if ($scopes === []) {
            $this->cache->forget($this->scopesIndexKey($feature));

            $this->putFeatures(array_values(array_diff($this->features(), [$feature])));

            return;
        }

        $this->putScopes($feature, $scopes);
    }

    protected function key(string $feature, mixed $scope): string
    {
        return $this->keyForScope($feature, Feature::serializeScope($scope));
    }

    protected function keyForScope(string $feature, string $scopeKey): string
    {
        return "{$this->prefix}:{$feature}:{$scopeKey}";
    }

    protected function scopesIndexKey(string $feature): string
    {
        return "{$this->indexKey}:{$feature}";
    }

    protected function put(string $key, mixed $value): void
    {
        if ($this->ttl === null) {
            $this->cache->forever($key, $value);

            return;
        }

        $this->cache->put($key, $value, $this->ttl);
    }
}
