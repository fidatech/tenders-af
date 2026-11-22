<?php

namespace App\Support;

use App\Models\Admin;
use Illuminate\Support\Str;

class AdminAccess
{
    public const PERMISSIONS = [
        'messages' => 'Contact companies and bidders',
        'verification' => 'Review company licences and project evidence',
        'office' => 'Submit office collections, expenses and handovers',
        'procurement' => 'Manage tenders, products and categories',
    ];

    private const OWN = ['admin.logout', 'admin.profile.*'];

    private const OWNER_ONLY = ['admin.admins.*', 'admin.settings.*', 'admin.branding.*', 'admin.modules.update', 'admin.logs.*', 'admin.companies.destroy', 'admin.office.review', 'admin.office.approvals'];

    private const FINANCE = ['admin.accounts.*', 'admin.expenses.*', 'admin.plans.*', 'admin.subscriptions.*', 'admin.products.payments.*'];

    public static function allows(Admin $admin, string $name, bool $readOnly = true): bool
    {
        if ($admin->is_active === false) {
            return $name === 'admin.logout';
        }
        if ($admin->isSuper() || Str::is(self::OWN, $name)) {
            return true;
        }
        if (Str::is(self::OWNER_ONLY, $name)) {
            return false;
        }
        if (in_array($admin->role, ['manager', 'viewer'], true)) {
            if ($admin->role === 'viewer') {
                return $readOnly && ! Str::is('admin.office.*', $name);
            }

            return ! Str::is(self::FINANCE, $name) || ($readOnly && ! Str::is('*.create', $name) && ! Str::is('*.edit', $name) && $name !== 'admin.subscriptions.reject');
        }

        $permissions = match ($admin->role) {
            'focal' => ['messages'],
            'cashier' => ['office', 'messages'],
            'verifier' => ['verification', 'messages'],
            'staff' => $admin->permissions ?? [],
            default => [],
        };

        foreach ($permissions as $permission) {
            $routes = match ($permission) {
                'messages' => ['admin.messages.*'],
                'verification' => ['admin.verification.*', 'admin.projects.*'],
                'office' => ['admin.office.*'],
                'procurement' => ['admin.tenders.*', 'admin.products.index', 'admin.products.show', 'admin.products.approve', 'admin.products.reject', 'admin.products.hide', 'admin.products.destroy', 'admin.industries.*', 'admin.categories.*'],
                default => [],
            };
            if (Str::is($routes, $name)) {
                return true;
            }
        }

        return false;
    }
}
