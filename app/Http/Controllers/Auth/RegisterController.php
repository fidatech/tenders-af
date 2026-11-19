<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Province;
use App\Support\Platform;
use App\Support\Companies;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * A company or an organisation registers by itself, for free, and says what it does here:
 * look for tenders, post tenders, or both. It starts on the free plan.
 */
class RegisterController extends Controller
{
    public function show(): View
    {
        abort_unless(Platform::bool('registration'), 404);

        return view('auth.register', ['kinds' => Company::KINDS, 'provinces' => Province::options()]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(Platform::bool('registration'), 404);

        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'company' => ['required', 'string', 'max:150'],
            'kind' => ['required', Rule::in(array_keys(Company::KINDS))],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => [Rule::in(['supplier', 'buyer'])],
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'province_id' => ['nullable', 'exists:provinces,id'],
            'country' => ['nullable', 'string', 'max:100'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'terms' => ['accepted'],
        ], ['terms.accepted' => __('Please accept the terms to register.'), 'roles.required' => __('Please choose what you want to do: find tenders, post tenders, or both.')]);

        // an Afghan company pays in AFN; an NGO or an international client in USD
        $company = Companies::create([
            'name' => $data['company'],
            'kind' => $data['kind'],
            'is_supplier' => in_array('supplier', $data['roles'], true),
            'is_buyer' => in_array('buyer', $data['roles'], true),
            'owner_name' => $data['name'],
            'owner_email' => $data['email'],
            'owner_password' => $data['password'],
            'phone' => $data['phone'] ?? null,
            'province_id' => $data['province_id'] ?? null,
            'country' => in_array($data['kind'], ['company', 'government'], true) ? 'Afghanistan' : ($data['country'] ?? null),
        ]);

        Auth::guard('web')->login($company->owner());
        $request->session()->regenerate();

        // a buyer goes straight to its tenders; a company that looks for tenders first chooses its sectors
        if (! $company->is_supplier) {
            return redirect()->route('posted.index')->with('success', __('Welcome! Your organisation is registered. Post your first tender.'));
        }

        return redirect()->route('settings.edit')->with('success', __('Welcome! Your company is registered. Choose your sectors to get matching tenders.'));
    }
}
