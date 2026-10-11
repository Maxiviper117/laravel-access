<?php

namespace Maxiviper117\Access\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use InvalidArgumentException;

/**
 * @property string $name
 * @property string|null $label
 * @property string|null $description
 * @property bool $is_system
 */
class Permission extends Model
{
    protected $table = 'access_permissions';

    protected $guarded = ['is_system'];

    protected $attributes = [
        'is_system' => false,
    ];

    protected $casts = [
        'is_system' => 'bool',
    ];

    #[\Override]
    protected static function booted(): void
    {
        static::deleting(function (self $permission): void {
            if ($permission->is_system) {
                return;
            }

            $hasRoleAttachments = $permission->roles()->exists();
            $hasDirectAssignments = Assignment::query()
                ->where('permission_id', $permission->getKey())
                ->exists();

            if ($hasRoleAttachments || $hasDirectAssignments) {
                throw new InvalidArgumentException('Cannot delete a dynamic permission while it is attached to a role or directly assigned.');
            }
        });
    }

    /** @return BelongsToMany<Role, $this> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'access_role_permissions');
    }
}
