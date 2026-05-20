<?php

return [
    'user_model' => 'App\\Models\\User',

    'permission_enums' => [],

    'default_scope_model' => null,

    'teams' => [
        'model' => null,
        'singular' => 'team',
        'plural' => 'teams',
    ],

    'invitations' => [
        'require_existing_user' => false,
        'expiry_days' => 7,
        'redirect_after_accept' => 'dashboard',
    ],

    'cache' => [
        /** @phpstan-ignore-next-line */
        'enabled' => env('APP_ENV') !== 'testing',
        'key' => 'access.permissions',
        'ttl' => null,
    ],

    'global_roles' => [
        // 'Platform Admin' => [
        //     App\Enums\Permission::SystemManage,
        // ],
    ],

    'roles' => [
        // 'Owner' => [
        //     App\Enums\Permission::UsersView,
        // ],
    ],

    'gate_before' => [
        'enabled' => false,
        'global_role' => 'Platform Admin',
    ],
];
