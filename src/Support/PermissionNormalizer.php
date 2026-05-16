<?php

namespace Maxiviper117\Access\Support;

use BackedEnum;
use InvalidArgumentException;

class PermissionNormalizer
{
    public function normalize(BackedEnum|string $permission): string
    {
        if ($permission instanceof BackedEnum) {
            return (string) $permission->value;
        }

        if ($permission === '') {
            throw new InvalidArgumentException('Permission name cannot be empty.');
        }

        return $permission;
    }

    public function normalizeMany(iterable $permissions): array
    {
        $names = [];

        foreach ($permissions as $permission) {
            $names[] = $this->normalize($permission);
        }

        return array_values(array_unique($names));
    }
}
