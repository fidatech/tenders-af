<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\BusinessMail;
use App\Models\AdminLog;
use App\Models\Company;
use App\Models\Notice;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/** Companies that sent their licence and wait to be verified: look at the document, then accept or refuse. */
class VerificationController extends Controller
{
    /** Why a licence can be refused. The administrator picks one; the company reads the text. */
    public const REASONS = [
        'unclear' => 'The document cannot be read. Please send a clear scan or photo.',
        'expired' => 'The licence has expired. Please send the renewed licence.',
        'mismatch' => 'The name on the licence is not the name of the company on the site.',
        'not_license' => 'The document is not a business licence.',
        'incomplete' => 'Part of the licence is missing. Please send all of its pages.',
    ];

    public function index(Request $request): View
    {
        $status = in_array($request->query('status'), ['pending', 'verified', 'rejected'], true) ? $request->query('status') : 'pending';

        return view('admin.verification', [
            'status' => $status,
            'rows' => Company::with('province')->where('verify_status', $status)->orderBy('verify_requested_at')->paginate(25)->withQueryString(),
            'waiting' => Company::where('verify_status', 'pending')->count(),
            'reasons' => self::REASONS,
        ]);
    }

    public function approve(int $id): RedirectResponse
    {
        $company = Company::where('verify_status', 'pending')->findOrFail($id);
        $company->update(['verify_status' => 'verified', 'verified_at' => now(), 'verify_note' => null]);
        AdminLog::record('verified company', $company->name);
        $this->tell($company, 'Your company is verified', ['We checked the licence of '.$company->name.'. Your public profile now carries the Verified badge.'], route('verification.show'));

        return back()->with('success', __(':name is verified.', ['name' => $company->name]));
    }

    public function reject(Request $request, int $id): RedirectResponse
    {
        $company = Company::where('verify_status', 'pending')->findOrFail($id);
        $data = $request->validate(['reason' => ['required', Rule::in(array_keys(self::REASONS))]]);
        $text = self::REASONS[$data['reason']];
        $company->update(['verify_status' => 'rejected', 'verify_note' => $text, 'verified_at' => null]);
        AdminLog::record('refused verification', $company->name, $text);
        $this->tell($company, 'Your verification was not accepted', [$text, 'You can send the document again from the Verification page.'], route('verification.show'));

        return back()->with('success', __('The company is told why.'));
    }

    /** The licence a company sent. */
    public function document(int $id): StreamedResponse
    {
        $company = Company::findOrFail($id);
        abort_unless($company->verify_doc_path && Storage::disk('local')->exists($company->verify_doc_path), 404);

        return Storage::disk('local')->response($company->verify_doc_path);
    }

    /** A notification under the bell of every user of the company and an email to its owner. */
    private function tell(Company $company, string $subject, array $lines, string $url): void
    {
        foreach (User::where('company_id', $company->id)->where('is_active', true)->get() as $user) {
            Notice::create(['company_id' => $company->id, 'user_id' => $user->id, 'title' => $subject, 'body' => $lines[0], 'url' => $url]);
        }
        if ($to = $company->owner()?->email) {
            try {
                Mail::to($to)->send(new BusinessMail($company, $subject, $lines, 'Open Verification', $url));
            } catch (Throwable $e) {
                report($e);
            }
        }
    }
}
