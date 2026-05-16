<?php

namespace Maxiviper117\Access\Concerns;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Maxiviper117\Access\Data\AccessContext;
use Maxiviper117\Access\Models\Assignment;

trait HasAccess
{
    public function accessAssignments(): MorphMany
    {
        return $this->morphMany(Assignment::class, 'actor');
    }

    public function in(Model $scope): AccessContext
    {
        return new AccessContext($this, $scope);
    }

    public function access(): AccessContext
    {
        return new AccessContext($this);
    }

    public function assignGlobalRole(string $role): self
    {
        $this->access()->assignRole($role);

        return $this;
    }

    public function removeGlobalRole(string $role): self
    {
        $this->access()->removeRole($role);

        return $this;
    }

    public function hasGlobalRole(string $role): bool
    {
        return $this->access()->hasRole($role);
    }

    public function canGlobally(BackedEnum|string $permission): bool
    {
        return $this->access()->can($permission);
    }

    public function giveGlobalPermission(BackedEnum|string $permission): self
    {
        $this->access()->givePermission($permission);

        return $this;
    }
}
