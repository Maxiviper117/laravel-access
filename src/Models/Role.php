<?php

namespace Maxiviper117\Access\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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

    /** @return BelongsToMany<Permission, $this> */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'access_role_permissions');
    }
}
