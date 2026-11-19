<?php

namespace App\Http\Controllers;

use App\Models\Notice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** The signed-in user's own name, email, password and notifications. */
class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', ['user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
        ]);
        $user->update($data);

        return back()->with('success', __('Your profile is saved.'));
    }

    public function password(): View
    {
        return view('profile.password');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::min(8)],
        ]);
        $request->user()->update(['password' => $data['password']]);

        return back()->with('success', __('Your password is changed.'));
    }

    public function notices(Request $request): View
    {
        return view('notices.index', ['rows' => Notice::where('user_id', $request->user()->id)->orderByDesc('id')->paginate(25)]);
    }

    /** Open a notification: it is marked as read and the user goes to what it is about. */
    public function openNotice(Request $request, int $id): RedirectResponse
    {
        $notice = Notice::where('user_id', $request->user()->id)->findOrFail($id);
        $notice->update(['read_at' => $notice->read_at ?? now()]);

        return $notice->url ? redirect($notice->url) : redirect()->route('notices.index');
    }

    public function readNotices(Request $request): RedirectResponse
    {
        Notice::where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return back();
    }
}
