<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminLog;
use App\Support\Platform;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Settings of the platform, so nothing needs a change in .env after the installation. */
class SettingsController extends Controller
{
    public function edit(): View
    {
        $values = [];
        foreach (array_keys(Platform::DEFAULTS) as $key) {
            $values[$key] = Platform::get($key);
        }
        $values['registration'] = Platform::bool('registration');
        $values['posting_free'] = Platform::bool('posting_free');

        return view('admin.settings', ['values' => $values]);
    }

    public function update(Request $request): RedirectResponse
    {
        $days = ['nullable', 'string', 'max:60', 'regex:/^\s*\d{1,3}(\s*[,;\s]\s*\d{1,3})*\s*$/'];
        $data = $request->validate([
            'trial_days' => ['required', 'integer', 'between:0,365'],
            'renew_open_days' => ['required', 'integer', 'between:0,365'],
            'whatsapp_url' => ['nullable', 'url', 'max:255'],
            'whatsapp_token' => ['nullable', 'string', 'max:255'],
            'billing_email' => ['nullable', 'string', 'max:500', function (string $attribute, mixed $value, \Closure $fail) {
                foreach (preg_split('/[\s,;]+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY) as $email) {
                    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $fail(__(':email is not an email address.', ['email' => $email]));
                    }
                }
            }],
            'support_email' => ['nullable', 'email', 'max:255'],
            'support_whatsapp' => ['nullable', 'string', 'max:50'],
            'trial_warn_days' => $days,
            'renew_warn_days' => $days,
            'receipt_prefix' => ['nullable', 'string', 'max:10'],
            'company_name' => ['nullable', 'string', 'max:150'],
            'company_address' => ['nullable', 'string', 'max:500'],
            'products_free_limit' => ['required', 'integer', 'between:0,1000'],
            'sponsor_week_afn' => ['required', 'numeric', 'min:0'],
            'sponsor_month_afn' => ['required', 'numeric', 'min:0'],
            'sponsor_week_usd' => ['required', 'numeric', 'min:0'],
            'sponsor_month_usd' => ['required', 'numeric', 'min:0'],
            'stats_min' => ['required', 'integer', 'between:0,100000'],
            'google_site_verification' => ['nullable', 'string', 'max:200'],
            'bing_site_verification' => ['nullable', 'string', 'max:200'],
        ], [
            'trial_warn_days.regex' => __('Write the days as numbers with commas between them, for example 7, 1.'),
            'renew_warn_days.regex' => __('Write the days as numbers with commas between them, for example 7, 1.'),
        ]);

        $data['registration'] = $request->boolean('registration') ? '1' : '0';
        $data['posting_free'] = $request->boolean('posting_free') ? '1' : '0';
        $data['billing_email'] = implode(', ', preg_split('/[\s,;]+/', (string) ($data['billing_email'] ?? ''), -1, PREG_SPLIT_NO_EMPTY));

        foreach (['google_site_verification', 'bing_site_verification'] as $key) {
            // Accept the whole meta tag, "google-site-verification=CODE" or the code alone.
            $code = trim((string) ($data[$key] ?? ''));
            if (preg_match('/content=["\']([^"\']+)["\']/', $code, $m)) {
                $code = $m[1];
            }
            $code = preg_replace('/^[a-z\-]*verification\s*=\s*/i', '', $code);
            $data[$key] = preg_replace('/[^A-Za-z0-9_\-]/', '', $code);
        }

        Platform::set($data);
        AdminLog::record('changed settings');

        return back()->with('success', __('Settings saved.'));
    }
}
