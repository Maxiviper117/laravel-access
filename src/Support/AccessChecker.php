<?php

namespace Maxiviper117\Access\Support;

use Illuminate\Database\Eloquent\Model;
use Maxiviper117\Access\Models\Assignment;
use Maxiviper117\Access\Models\Permission;
use Maxiviper117\Access\Models\Role;

class AccessChecker
{
    /** @return array<int, string> */
    public function permissionsFor(Model $actor, ?Model $scope): array
    {
        return app(AccessCache::class)->remember($actor, $scope, function () use ($actor, $scope): array {
            $query = Assignment::query()
                ->where('actor_type', $actor->getMorphClass())
                ->where('actor_id', $actor->getKey())
                ->with(['permission', 'role.permissions']);

            $scope instanceof Model
                ? $query->where('scope_type', $scope->getMorphClass())->where('scope_id', $scope->getKey())
                : $query->whereNull('scope_type')->whereNull('scope_id');

            $permissions = [];

            foreach ($query->get() as $assignment) {
                if ($assignment->permission instanceof Permission) {
                    $permissions[] = $assignment->permission->name;
                }

                if ($assignment->role instanceof Role) {
                    array_push($permissions, ...$assignment->role->permissions->pluck('name')->all());
                }
            }

            return array_values(array_unique(array_map(fn ($v): string => is_string($v) ? $v : (is_scalar($v) || is_null($v) ? (string) $v : ''), $permissions)));
        });
    }

    public function can(Model $actor, ?Model $scope, string $permission): bool
    {
        return in_array($permission, $this->permissionsFor($actor, $scope), true);
    }
}
