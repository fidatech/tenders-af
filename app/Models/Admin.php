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
        return ['password' => 'hashed', 'is_active' => 'boolean'];
    }

    public const ROLES = ['super' => 'Super administrator', 'manager' => 'Manager', 'viewer' => 'Viewer'];

    public function isSuper(): bool
    {
        return ($this->role ?? 'super') === 'super';
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
