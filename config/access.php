<?php

return [
    'user_model' => 'App\\Models\\User',

    'permission_enum' => null,

    'default_scope_model' => null,

    'cache' => [
        'enabled' => ! app()->environment('testing'),
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
