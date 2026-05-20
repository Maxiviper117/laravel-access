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
 * @property Role|null $role
 * @property Permission|null $permission
 */
class Assignment extends Model
{
    protected $table = 'access_assignments';

    protected $guarded = [];

    public function actor(): MorphTo
    {
        return $this->morphTo();
    }

    public function scope(): MorphTo
    {
        return $this->morphTo();
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }

    protected static function booted(): void
    {
        static::saved(fn (self $assignment) => app(AccessCache::class)->forgetForAssignment($assignment));
        static::deleted(fn (self $assignment) => app(AccessCache::class)->forgetForAssignment($assignment));
    }
}
