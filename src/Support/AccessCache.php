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
            $result = $callback();

            return is_array($result) ? array_values(array_map(fn ($v): string => is_string($v) ? $v : (is_scalar($v) || is_null($v) ? (string) $v : ''), $result)) : [];
        }

        $ttl = config('access.cache.ttl');

        if (! is_int($ttl)) {
            $ttl = null;
        }

        $result = Cache::remember($this->key($actor, $scope), $ttl, fn (): mixed => $callback());

        return is_array($result) ? array_values(array_map(fn ($v): string => is_string($v) ? $v : (is_scalar($v) || is_null($v) ? (string) $v : ''), $result)) : [];
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

        $this->forgetIdentity(
            $assignment->actor_type,
            (string) $assignment->actor_id,
            $assignment->scope_type,
            $assignment->scope_id === null ? null : (string) $assignment->scope_id,
        );
    }

    public function forgetForAssignmentIdentity(string $actorType, string $actorId, ?string $scopeType, ?string $scopeId): void
    {
        if (! config('access.cache.enabled')) {
            return;
        }

        $this->forgetIdentity($actorType, $actorId, $scopeType, $scopeId);
    }

    private function forgetIdentity(string $actorType, string $actorId, ?string $scopeType, ?string $scopeId): void
    {
        Cache::forget($this->identityKey($actorType, $actorId, $scopeType, $scopeId));
    }

    private function key(Model $actor, ?Model $scope): string
    {
        return $this->identityKey(
            (string) $actor->getMorphClass(),
            is_scalar($actor->getKey()) || is_null($actor->getKey()) ? (string) $actor->getKey() : '',
            $scope instanceof Model ? (string) $scope->getMorphClass() : null,
            $scope instanceof Model && (is_scalar($scope->getKey()) || is_null($scope->getKey()))
                ? (string) $scope->getKey()
                : ($scope instanceof Model ? 'global' : null),
        );
    }

    private function identityKey(string $actorType, string $actorId, ?string $scopeType, ?string $scopeId): string
    {
        return hash('sha256', serialize([
            $this->baseKey(),
            $this->version(),
            $actorType,
            $actorId,
            $scopeType,
            $scopeId,
        ]));
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
