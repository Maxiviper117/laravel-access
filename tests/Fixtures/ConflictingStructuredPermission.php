<?php

declare(strict_types=1);

namespace Maxiviper117\Access\Tests\Fixtures;

use Maxiviper117\Access\Contracts\DescribesPermission;
use Maxiviper117\Access\Data\PermissionMetadata;

enum ConflictingStructuredPermission: string implements DescribesPermission
{
    case InvoicesView = 'invoices.view';

    public function metadata(): PermissionMetadata
    {
        return PermissionMetadata::field('invoices', 'summary', 'view');
    }
}
