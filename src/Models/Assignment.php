<?php

namespace Maxiviper117\Access\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Maxiviper117\Access\Support\AccessCache;

/**
 * @property string $actor_type
 * @property int $actor_id
 * @property string|null $scope_type
 * @property int|null $scope_id
 * @property int|null $role_id
 * @property int|null $permission_id
 * @property Role|null $role
 * @property Permission|null $permission
 */
class Assignment extends Model
{
    protected $table = 'access_assignments';

    protected $fillable = [
        'actor_type',
        'actor_id',
        'role_id',
        'permission_id',
        'scope_type',
        'scope_id',
    ];

    /** @return MorphTo<Model, $this> */
    public function actor(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return MorphTo<Model, $this> */
    public function scope(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<Role, $this> */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /** @return BelongsTo<Permission, $this> */
    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }

    #[\Override]
    protected static function booted(): void
    {
        static::saving(function (self $assignment): void {
            if (($assignment->role_id === null) === ($assignment->permission_id === null)) {
                throw new \InvalidArgumentException('An assignment must reference exactly one role or permission.');
            }

            if (($assignment->scope_type === null) !== ($assignment->scope_id === null)) {
                throw new \InvalidArgumentException('An assignment scope must include both scope_type and scope_id.');
            }
        });

        static::saved(function (self $assignment): void {
            $cache = app(AccessCache::class);
            $original = $assignment->getRawOriginal();

            if (array_key_exists('actor_type', $original) && $assignment->isDirty([
                'actor_type',
                'actor_id',
                'scope_type',
                'scope_id',
            ])) {
                $cache->forgetForAssignmentIdentity(
                    (string) $original['actor_type'],
                    (string) $original['actor_id'],
                    isset($original['scope_type']) ? (string) $original['scope_type'] : null,
                    isset($original['scope_id']) ? (string) $original['scope_id'] : null,
                );
            }

            $cache->forgetForAssignment($assignment);
        });
        static::deleted(fn (self $assignment) => app(AccessCache::class)->forgetForAssignment($assignment));
    }
}
