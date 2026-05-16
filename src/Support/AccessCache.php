<?php

namespace Maxiviper117\Access\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Maxiviper117\Access\Models\Assignment;

class AccessCache
{
    public function remember(Model $actor, ?Model $scope, callable $callback): array
    {
        if (! config('access.cache.enabled')) {
            return $callback();
        }

        return Cache::remember($this->key($actor, $scope), config('access.cache.ttl'), $callback);
    }

    public function forget(Model $actor, ?Model $scope = null): void
    {
        Cache::forget($this->key($actor, $scope));
    }

    public function clear(): void
    {
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
            $assignment->actor_id,
            $assignment->scope_type ?? 'global',
            $assignment->scope_id ?? 'global',
        ));
    }

    private function key(Model $actor, ?Model $scope): string
    {
        return sprintf(
            '%s:%s:%s:%s:%s:%s',
            $this->baseKey(),
            $this->version(),
            $actor->getMorphClass(),
            $actor->getKey(),
            $scope?->getMorphClass() ?? 'global',
            $scope?->getKey() ?? 'global',
        );
    }

    private function baseKey(): string
    {
        return config('access.cache.key', 'access.permissions');
    }

    private function versionKey(): string
    {
        return $this->baseKey().':version';
    }

    private function version(): int
    {
        return (int) Cache::get($this->versionKey(), 1);
    }
}
