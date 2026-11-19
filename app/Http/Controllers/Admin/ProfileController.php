<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** The signed-in administrator's own name, email and password. */
class ProfileController extends Controller
{
    public function edit(): View
    {
        return view('admin.profile', ['admin' => auth('admin')->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $admin = auth('admin')->user();
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255', Rule::unique('admins')->ignore($admin->id)],
            // a new password is asked for only when the box is filled in, and then the current one too
            'current_password' => ['nullable', 'required_with:password', 'current_password:admin'],
            'password' => ['nullable', 'confirmed', 'different:current_password', Password::min(8)],
        ]);

        $admin->fill(['name' => $data['name'], 'email' => $data['email']]);
        if (! empty($data['password'])) {
            $admin->password = $data['password'];
        }
        $admin->save();

        return back()->with('success', empty($data['password']) ? __('Your profile is saved.') : __('Your profile and your new password are saved.'));
    }
}
