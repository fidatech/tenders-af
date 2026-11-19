<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * How the company hears about new tenders: by email and by WhatsApp, and for which kinds of
 * tender. Which sectors and categories it hears about is chosen on the company profile.
 */
class AlertController extends Controller
{
    public function edit(): View
    {
        $company = company();

        return view('alerts.edit', [
            'company' => $company,
            'types' => config('tenders.types'),
            'mine' => $company->alert_types ?? [],
            'industries' => $company->industries()->orderBy('name')->pluck('name')->all(),
            'categories' => $company->categories()->orderBy('name')->pluck('name')->all(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'notify_email' => ['nullable', 'email', 'max:255'],
            'whatsapp' => ['nullable', 'string', 'max:50', 'regex:/^\+?[0-9 ()-]{7,20}$/'],
            'alert_types' => ['nullable', 'array'],
            'alert_types.*' => [Rule::in(array_keys(config('tenders.types')))],
        ], ['whatsapp.regex' => __('Write the WhatsApp number with its country code, for example +93 70 000 0000.')]);

        company()->update([
            'notify_email' => $data['notify_email'] ?? null,
            'whatsapp' => $data['whatsapp'] ?? null,
            'alert_email' => $request->boolean('alert_email'),
            // WhatsApp messages go only to a company that asked for them
            'alert_whatsapp' => $request->boolean('alert_whatsapp') && filled($data['whatsapp'] ?? null),
            'alert_types' => array_values($data['alert_types'] ?? []),
        ]);

        return back()->with('success', __('Your tender alerts are saved.'));
    }
}
