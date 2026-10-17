<?php

namespace Maxiviper117\Access\Contracts;

use Maxiviper117\Access\Data\PermissionMetadata;

interface DescribesPermission
{
    public function metadata(): ?PermissionMetadata;
}
