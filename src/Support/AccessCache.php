<?php

namespace Maxiviper117\Access\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Maxiviper117\Access\Models\Assignment;

class AccessCache
{
    /** @return array<int, string> */
    public function remember(Model $actor, ?Model $scope, callable $callback): array
    {
        if (! config('access.cache.enabled')) {
            return $callback();
        }

        $ttl = config('access.cache.ttl');

        if (! is_int($ttl)) {
            $ttl = null;
        }

        $result = Cache::remember($this->key($actor, $scope), $ttl, fn (): mixed => $callback());

        return is_array($result) ? array_map(fn ($v): string => is_string($v) ? $v : (is_scalar($v) || is_null($v) ? (string) $v : ''), $result) : [];
    }

    public function forget(Model $actor, ?Model $scope = null): void
    {
        if (! config('access.cache.enabled')) {
            return;
        }

        Cache::forget($this->key($actor, $scope));
    }

    public function clear(): void
    {
        if (! config('access.cache.enabled')) {
            return;
        }

        Cache::forever($this->versionKey(), $this->version() + 1);
    }

    public function forgetForAssignment(Assignment $assignment): void
    {
        if (! config('access.cache.enabled')) {
            return;
        }

        Cache::forget(sprintf(
            '%s:%s:%s:%s:%s:%s',
            $this->baseKey(),
            $this->version(),
            $assignment->actor_type,
            (string) $assignment->actor_id,
            $assignment->scope_type ?? 'global',
            (string) ($assignment->scope_id ?? 'global'),
        ));
    }

    private function key(Model $actor, ?Model $scope): string
    {
        return sprintf(
            '%s:%s:%s:%s:%s:%s',
            $this->baseKey(),
            $this->version(),
            $actor->getMorphClass(),
            (is_scalar($actor->getKey()) || is_null($actor->getKey()) ? (string) $actor->getKey() : ''),
            $scope?->getMorphClass() ?? 'global',
            $scope !== null && (is_scalar($scope->getKey()) || is_null($scope->getKey())) ? (string) $scope->getKey() : 'global',
        );
    }

    private function baseKey(): string
    {
        return is_string(config('access.cache.key')) ? config('access.cache.key') : 'access.permissions';
    }

    private function versionKey(): string
    {
        return $this->baseKey().':version';
    }

    private function version(): int
    {
        $version = Cache::get($this->versionKey(), 1);

        return is_int($version) ? $version : (is_numeric($version) ? (int) $version : 1);
    }
}
