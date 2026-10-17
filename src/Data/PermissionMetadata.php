<?php

namespace Maxiviper117\Access\Data;

use InvalidArgumentException;
use Maxiviper117\Access\Enums\PermissionKind;

final readonly class PermissionMetadata
{
    private function __construct(
        public string $resource,
        public PermissionKind $kind,
        public ?string $target,
        public string $ability,
    ) {
        self::assertIdentifier($resource, 'resource');
        self::assertIdentifier($ability, 'ability');

        if ($kind === PermissionKind::Field) {
            if ($target === null) {
                throw new InvalidArgumentException('Field permission metadata requires a target.');
            }

            self::assertIdentifier($target, 'target');
        }
    }

    public static function resource(string $resource, string $ability): self
    {
        return new self($resource, PermissionKind::Resource, null, $ability);
    }

    public static function field(string $resource, string $target, string $ability): self
    {
        return new self($resource, PermissionKind::Field, $target, $ability);
    }

    /** @return array{resource: string, kind: string, target: ?string, ability: string} */
    public function toArray(): array
    {
        return [
            'resource' => $this->resource,
            'kind' => $this->kind->value,
            'target' => $this->target,
            'ability' => $this->ability,
        ];
    }

    private static function assertIdentifier(string $value, string $field): void
    {
        if (trim($value) === '') {
            throw new InvalidArgumentException("Permission metadata {$field} cannot be empty.");
        }

        if (mb_strlen($value) > 255) {
            throw new InvalidArgumentException("Permission metadata {$field} cannot exceed 255 characters.");
        }
    }
}
