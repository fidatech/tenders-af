<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\CrudController;
use App\Models\Admin;
use App\Models\OfficeEntry;
use App\Support\AdminAccess;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** The system administrators themselves. */
class AdminController extends CrudController
{
    protected string $model = Admin::class;

    protected string $route = 'admin.admins';

    protected string $layout = 'layouts.admin';

    protected string $title = 'Administrators';

    protected string $singular = 'Administrator';

    protected string $icon = 'user-shield';

    protected array $search = ['name', 'email'];

    protected function fields(?Model $record): array
    {
        return [
            ['name' => 'name', 'label' => 'Full name', 'rules' => ['required', 'string', 'max:150']],
            ['name' => 'email', 'label' => 'Email address', 'type' => 'email', 'rules' => ['required', 'email', 'max:255', Rule::unique('admins')->ignore($record?->id)]],
            ['name' => 'password', 'label' => 'Password', 'type' => 'password', 'rules' => [$record ? 'nullable' : 'required', Password::min(8)], 'help' => $record ? 'Leave empty to keep the password.' : 'At least 8 characters'],
            ['name' => 'role', 'label' => 'Role', 'type' => 'select', 'default' => 'manager', 'rules' => ['required', Rule::in(array_keys(Admin::ROLES))], 'col' => 12,
                'options' => [
                    'super' => __('Super administrator: everything'),
                    'manager' => __('Manager: businesses and money, but not administrators, settings, the activity log or deleting a business'),
                    'focal' => __('Focal point: messages and own account'),
                    'cashier' => __('Cashier: collections, expenses, handovers and messaging'),
                    'verifier' => __('Company verifier: licence review and messaging'),
                    'staff' => __('Custom office staff: permissions selected below'),
                    'viewer' => __('Viewer: looks at what a manager sees and changes nothing'),
                ]],
            ...collect(AdminAccess::PERMISSIONS)->map(fn ($label, $key) => [
                'name' => 'permission_'.$key, 'label' => $label, 'type' => 'checkbox', 'virtual' => true,
                'value' => in_array($key, $record?->permissions ?? [], true),
                'help' => 'Applies to the Custom office staff role.',
            ])->values()->all(),
            ['name' => 'is_active', 'label' => 'May sign in', 'type' => 'checkbox', 'default' => true],
        ];
    }

    protected function columns(): array
    {
        return [
            'Full name' => fn (Admin $a) => $a->name,
            'Email address' => fn (Admin $a) => $a->email,
            'Role' => fn (Admin $a) => $a->roleLabel(),
            'May sign in' => fn (Admin $a) => $a->is_active ? __('Yes') : __('No'),
        ];
    }

    protected function beforeSave(array $data, ?Model $record, Request $request): array
    {
        $data['email'] = strtolower($data['email']);
        if ($record && $record->id === auth('admin')->id()) {
            $data['is_active'] = true; // nobody locks himself out
            $data['role'] = $record->role;
        }
        $data['permissions'] = $data['role'] === 'staff'
            ? array_values(array_filter(array_keys(AdminAccess::PERMISSIONS), fn ($key) => $request->boolean('permission_'.$key)))
            : null;
        // there must always be a super administrator who can sign in
        if ($record && $record->isSuper() && ($data['role'] !== 'super' || ! $data['is_active'])
            && ! Admin::where('role', 'super')->where('is_active', true)->where('id', '!=', $record->id)->exists()) {
            throw new \App\Exceptions\UserError(__('This is the only super administrator. Make another one first.'));
        }

        return $data;
    }

    protected function deleteProblem(Model $record): ?string
    {
        if ($record->id === auth('admin')->id()) {
            return __('You cannot delete yourself.');
        }

        return OfficeEntry::where('employee_id', $record->id)->orWhere('reviewed_by', $record->id)->exists()
            ? __('This employee has office records. Disable the account to keep the audit history.')
            : null;
    }
}
