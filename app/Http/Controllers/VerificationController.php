<?php

namespace App\Http\Controllers;

use App\Exceptions\UserError;
use App\Models\Admin;
use App\Mail\PlatformNotice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * A company asks to be verified by sending its business licence. A system administrator
 * checks it under /admin > Verification; a verified company carries a badge in the directory.
 */
class VerificationController extends Controller
{
    public function show(): View
    {
        return view('verification.show', ['company' => company()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $company = company();
        if ($company->isVerified()) {
            throw new UserError(__('Your company is already verified.'));
        }
        if ($company->verify_status === 'pending') {
            throw new UserError(__('Your documents are already waiting for review.'));
        }

        $data = $request->validate([
            'license_no' => ['required', 'string', 'max:100'],
            'tin' => ['nullable', 'string', 'max:100'],
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ], ['document.required' => __('Please add a scan or a photo of your business licence.')]);

        if ($company->verify_doc_path) {
            Storage::disk('local')->delete($company->verify_doc_path);
        }
        $company->update([
            'license_no' => $data['license_no'],
            'tin' => $data['tin'] ?? $company->tin,
            'verify_doc_path' => $request->file('document')->store('verification/'.$company->id, 'local'),
            'verify_status' => 'pending',
            'verify_note' => null,
            'verify_requested_at' => now(),
        ]);

        $this->tellAdministrators($company->name);

        return back()->with('success', __('Thank you. We will check your licence and tell you the result.'));
    }

    /** The licence the company sent, for the company itself. */
    public function document(): StreamedResponse
    {
        $company = company();
        abort_unless($company->verify_doc_path && Storage::disk('local')->exists($company->verify_doc_path), 404);

        return Storage::disk('local')->response($company->verify_doc_path);
    }

    /** A mail problem never loses the request itself. */
    private function tellAdministrators(string $name): void
    {
        $to = array_values(array_filter(array_map('trim', explode(',', (string) platform('billing_email')))))
            ?: Admin::where('is_active', true)->pluck('email')->all();
        if (! $to) {
            return;
        }
        try {
            Mail::to($to)->send(new PlatformNotice('A company asks to be verified', [
                $name.' sent its business licence and asks to be verified.',
                'Open the Verification page of the administration to check it.',
            ], 'Open Verification', route('admin.verification.index')));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
