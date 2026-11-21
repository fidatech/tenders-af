<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

/** A system administrator of Tenders.af: signs in at /admin and never sees inside a business. */
class Admin extends Authenticatable
{
    protected $guarded = [];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'is_active' => 'boolean', 'permissions' => 'array'];
    }

    public const ROLES = ['super' => 'Super administrator', 'manager' => 'Manager', 'viewer' => 'Viewer', 'focal' => 'Focal point', 'cashier' => 'Cashier', 'verifier' => 'Company verifier', 'staff' => 'Custom office staff'];

    public function isSuper(): bool
    {
        return ($this->role ?? 'super') === 'super';
    }

    public function homeRoute(): string
    {
        if (in_array($this->role ?? 'super', ['super', 'manager', 'viewer'], true)) {
            return 'admin.dashboard';
        }
        foreach (['admin.office.index', 'admin.verification.index', 'admin.messages.index', 'admin.tenders.index'] as $route) {
            if (\App\Support\AdminAccess::allows($this, $route)) {
                return $route;
            }
        }

        return 'admin.profile.edit';
    }

    public function roleLabel(): string
    {
        return __(self::ROLES[$this->role ?? 'super'] ?? $this->role);
    }

    public function initials(): string
    {
        return User::initialsOf($this->name);
    }
}
