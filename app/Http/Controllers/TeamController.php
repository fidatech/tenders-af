<?php

namespace App\Http\Controllers;

use App\Exceptions\UserError;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** The people of the company who may sign in. Only the owner opens this screen. */
class TeamController extends CrudController
{
    protected string $model = User::class;

    protected string $route = 'team';

    protected string $title = 'Team';

    protected string $singular = 'User';

    protected string $icon = 'user-group';

    protected array $search = ['name', 'email'];

    /** Users have no automatic filter, so every query here names the company. */
    protected function query(Request $request): Builder
    {
        return User::query()->where('company_id', company_id());
    }

    protected function fields(?Model $record): array
    {
        return [
            ['name' => 'name', 'label' => 'Full name', 'rules' => ['required', 'string', 'max:150']],
            ['name' => 'email', 'label' => 'Email address', 'type' => 'email', 'rules' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($record?->id)]],
            ['name' => 'password', 'label' => 'Password', 'type' => 'password', 'rules' => [$record ? 'nullable' : 'required', Password::min(8)], 'help' => $record ? 'Leave empty to keep the password.' : 'At least 8 characters'],
            ['name' => 'role', 'label' => 'Role', 'type' => 'select', 'default' => User::MEMBER, 'options' => [User::MEMBER => __('Member: everything except Team and Settings'), User::OWNER => __('Owner: everything')], 'rules' => ['required', Rule::in([User::MEMBER, User::OWNER])]],
            ['name' => 'is_active', 'label' => 'May sign in', 'type' => 'checkbox', 'default' => true],
        ];
    }

    protected function columns(): array
    {
        return [
            'Full name' => fn (User $u) => $u->name,
            'Email address' => fn (User $u) => $u->email,
            'Role' => fn (User $u) => $u->isOwner() ? __('Owner') : __('Member'),
            'May sign in' => fn (User $u) => $u->is_active ? __('Yes') : __('No'),
        ];
    }

    protected function beforeSave(array $data, ?Model $record, Request $request): array
    {
        $data['email'] = strtolower($data['email']);

        $limit = company()?->plan?->max_users;
        if (! $record && $limit && $this->query($request)->count() >= $limit) {
            throw new UserError(__('Your plan allows :count users. Please upgrade your plan to add more.', ['count' => $limit]));
        }
        // the company must always keep one owner who can sign in
        if ($record && $record->isOwner() && ($data['role'] !== User::OWNER || ! $data['is_active']) && ! $this->otherOwner($record)) {
            throw new UserError(__('This is the only owner. Make another user an owner first.'));
        }

        return $record ? $data : $data + ['company_id' => company_id()];
    }

    protected function deleteProblem(Model $record): ?string
    {
        if ($record->id === auth()->id()) {
            return __('You cannot delete yourself.');
        }

        return $record->isOwner() && ! $this->otherOwner($record) ? __('This is the only owner. Make another user an owner first.') : null;
    }

    private function otherOwner(Model $record): bool
    {
        return User::where('company_id', company_id())->where('role', User::OWNER)->where('is_active', true)->where('id', '!=', $record->id)->exists();
    }
}
