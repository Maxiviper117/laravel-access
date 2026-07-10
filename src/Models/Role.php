<?php

namespace Maxiviper117\Access\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use InvalidArgumentException;

/**
 * @property string $name
 * @property bool $is_global
 * @property bool $is_system
 * @property string|null $scope_type
 * @property int|null $scope_id
 * @property Collection<int, Permission> $permissions
 */
class Role extends Model
{
    protected $table = 'access_roles';

    protected $guarded = [];

    protected $casts = [
        'is_global' => 'bool',
        'is_system' => 'bool',
    ];

    #[\Override]
    protected static function booted(): void
    {
        static::saving(function (self $role): void {
            $hasScopeType = $role->scope_type !== null;
            $hasScopeId = $role->scope_id !== null;

            if ($hasScopeType !== $hasScopeId) {
                throw new InvalidArgumentException('A role scope must include both scope_type and scope_id.');
            }

        });
    }

    /** @return BelongsToMany<Permission, $this> */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'access_role_permissions');
    }
}
