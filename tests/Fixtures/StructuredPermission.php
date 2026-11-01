<?php

declare(strict_types=1);

namespace Maxiviper117\Access\Tests\Fixtures;

use Maxiviper117\Access\Contracts\DescribesPermission;
use Maxiviper117\Access\Data\PermissionMetadata;

enum StructuredPermission: string implements DescribesPermission
{
    case InvoicesView = 'invoices.view';
    case InternalCostView = 'invoices.fields.internal_cost.view';
    case InvoicesExport = 'invoices.export';

    public function metadata(): ?PermissionMetadata
    {
        return match ($this) {
            self::InvoicesView => PermissionMetadata::resource('invoices', 'view'),
            self::InternalCostView => PermissionMetadata::field('invoices', 'internal_cost', 'view'),
            self::InvoicesExport => null,
        };
    }
}
