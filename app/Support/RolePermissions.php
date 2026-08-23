<?php

namespace App\Support;

final class RolePermissions
{
    private const PERMISSIONS = [
        'owner' => [
            'dashboard.view', 'customers.view', 'customers.manage',
            'onts.view', 'onts.manage', 'network.view', 'network.manage', 'network.retry',
            'billing.view', 'payments.view', 'payments.create', 'payments.reversal',
            'incomes.view', 'incomes.create', 'expenses.view', 'expenses.create',
            'expenses.approve', 'notifications.view', 'devices.manage', 'reference.view',
        ],
        'admin_keuangan' => [
            'dashboard.view', 'customers.view', 'billing.view',
            'payments.view', 'payments.create', 'payments.reversal',
            'incomes.view', 'incomes.create', 'expenses.view', 'expenses.create',
            'notifications.view', 'devices.manage', 'reference.view',
        ],
        'admin_jaringan' => [
            'dashboard.view', 'customers.view', 'customers.manage',
            'onts.view', 'onts.manage', 'network.view', 'network.manage', 'network.retry',
            'notifications.view', 'devices.manage', 'reference.view',
        ],
    ];

    public static function forRole(string $role): array
    {
        return self::PERMISSIONS[$role] ?? [];
    }

    public static function allows(string $role, string $permission): bool
    {
        return in_array($permission, self::forRole($role), true);
    }
}
