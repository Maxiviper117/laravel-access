<?php

declare(strict_types=1);

namespace Maxiviper117\Access\Tests\Fixtures;

enum Permission: string
{
    case UsersView = 'users.view';
    case UsersInvite = 'users.invite';
    case RolesManage = 'roles.manage';
    case SystemManage = 'system.manage';
}
