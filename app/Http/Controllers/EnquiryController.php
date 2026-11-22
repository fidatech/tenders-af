<?php

namespace App\Http\Controllers;

use App\Exceptions\UserError;
use App\Models\AdminNotice;
use App\Models\Company;
use App\Models\Enquiry;
use App\Models\Notice;
use App\Support\Modules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Business Enquiries between companies. Any member of a company reads and answers its threads;
 * answering never needs a paid plan. A company only ever reaches the threads it is part of.
 */
class EnquiryController extends Controller
{
    public function index(Request $request): View
    {
        $companyId = $request->user()->company_id;
        $box = $request->query('box') === 'sent' ? 'sent' : 'inbox';
        $rows = Enquiry::with('sender:id,name,slug', 'receiver:id,name,slug')
            ->where($box === 'sent' ? 'from_company_id' : 'to_company_id', $companyId)
            ->orderByDesc('last_message_at')->paginate(25)->withQueryString();
        $unread = Enquiry::unreadFor($companyId)->pluck('id')->all();

        return view('enquiries.index', ['rows' => $rows, 'box' => $box, 'unread' => $unread, 'companyId' => $companyId]);
    }

    public function show(Request $request, int $id): View
    {
        $companyId = $request->user()->company_id;
        $enquiry = Enquiry::for($companyId)->with('messages', 'sender', 'receiver')->findOrFail($id);
        $enquiry->markRead($companyId);
        $other = $enquiry->otherSide($companyId);

        return view('enquiries.show', [
            'enquiry' => $enquiry,
            'other' => $other,
            'companyId' => $companyId,
            'blocked' => $other ? DB::table('company_blocks')->where('company_id', $companyId)->where('blocked_company_id', $other->id)->exists() : false,
        ]);
    }

    /** A new enquiry, sent from the page of the company it is for. */
    public function store(Request $request, string $slug): RedirectResponse
    {
        $user = $request->user();
        $sender = $user->company;
        $receiver = Company::listed()->where('slug', $slug)->firstOrFail();
        if (! Modules::allows('enquiries', $sender)) {
            throw new UserError(__('Sending enquiries is part of the paid plans.'));
        }
        if ($receiver->id === $sender->id) {
            throw new UserError(__('You cannot send an enquiry to your own company.'));
        }
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'min:10', 'max:5000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);
        // a company that blocked the sender is not told who tries; the sender only learns it cannot write
        if (DB::table('company_blocks')->where('company_id', $receiver->id)->where('blocked_company_id', $sender->id)->exists()) {
            throw new UserError(__(':company does not accept enquiries from your company.', ['company' => $receiver->name]));
        }
        if (Enquiry::where('from_company_id', $sender->id)->where('created_at', '>=', now()->subDay())->count() >= Enquiry::DAILY_LIMIT) {
            throw new UserError(__('Your company has sent :n enquiries today. Please wait until tomorrow.', ['n' => Enquiry::DAILY_LIMIT]));
        }

        $enquiry = DB::transaction(function () use ($data, $user, $sender, $receiver, $request) {
            $now = now();
            $enquiry = Enquiry::create([
                'from_company_id' => $sender->id, 'from_user_id' => $user->id, 'to_company_id' => $receiver->id,
                'subject' => $data['subject'], 'last_message_at' => $now, 'from_read_at' => $now,
            ]);
            $this->addMessage($enquiry, $user, $data['body'], $request->file('attachment'));

            return $enquiry;
        });
        $this->tell($enquiry, $receiver, __('New enquiry from :company', ['company' => $sender->name]));

        return redirect()->route('enquiries.show', ['id' => $enquiry->id])->with('success', __('Your enquiry was sent to :company.', ['company' => $receiver->name]));
    }

    public function reply(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        $enquiry = Enquiry::for($user->company_id)->with('sender', 'receiver')->findOrFail($id);
        if ($enquiry->status !== 'open') {
            throw new UserError(__('This enquiry is closed. Open it again to answer.'));
        }
        $other = $enquiry->otherSide($user->company_id);
        if ($other && DB::table('company_blocks')->where('company_id', $other->id)->where('blocked_company_id', $user->company_id)->exists()) {
            throw new UserError(__(':company does not accept messages from your company.', ['company' => $other->name]));
        }
        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        DB::transaction(function () use ($enquiry, $user, $data, $request) {
            $now = now();
            $this->addMessage($enquiry, $user, $data['body'], $request->file('attachment'));
            $mine = $enquiry->isReceiver($user->company_id);
            $enquiry->update(['last_message_at' => $now, $mine ? 'to_read_at' : 'from_read_at' => $now, $mine ? 'from_read_at' : 'to_read_at' => null]);
        });
        if ($other) {
            $this->tell($enquiry, $other, __('New message from :company', ['company' => $user->company->name]));
        }

        return back()->with('success', __('Sent.'));
    }

    /** Close or open again: either side may. */
    public function status(Request $request, int $id): RedirectResponse
    {
        $enquiry = Enquiry::for($request->user()->company_id)->findOrFail($id);
        $enquiry->update(['status' => $enquiry->status === 'open' ? 'closed' : 'open']);

        return back()->with('success', $enquiry->status === 'open' ? __('The enquiry is open again.') : __('The enquiry is closed.'));
    }

    /** Stop or allow messages from the other company. */
    public function block(Request $request, int $id): RedirectResponse
    {
        $companyId = $request->user()->company_id;
        $enquiry = Enquiry::for($companyId)->with('sender', 'receiver')->findOrFail($id);
        $other = $enquiry->otherSide($companyId);
        abort_unless($other, 404);
        $query = DB::table('company_blocks')->where('company_id', $companyId)->where('blocked_company_id', $other->id);
        if ($query->exists()) {
            $query->delete();

            return back()->with('success', __(':company may write to you again.', ['company' => $other->name]));
        }
        DB::table('company_blocks')->insert(['company_id' => $companyId, 'blocked_company_id' => $other->id, 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('success', __(':company can no longer send you enquiries or messages.', ['company' => $other->name]));
    }

    /** Report a thread to the administrators: only then may they read it. */
    public function report(Request $request, int $id): RedirectResponse
    {
        $companyId = $request->user()->company_id;
        $enquiry = Enquiry::for($companyId)->findOrFail($id);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $enquiry->update(['reported_by' => $companyId, 'report_reason' => $data['reason'], 'reported_at' => now()]);
        AdminNotice::toAll('message', __('An enquiry was reported'), $data['reason'], route('admin.enquiries.show', ['id' => $enquiry->id]));

        return back()->with('success', __('Thank you. The administrators will look at this thread.'));
    }

    public function attachment(Request $request, int $id, int $message): StreamedResponse
    {
        $enquiry = Enquiry::for($request->user()->company_id)->findOrFail($id);
        $row = $enquiry->messages()->whereNotNull('attachment_path')->findOrFail($message);
        abort_unless(Storage::disk('local')->exists($row->attachment_path), 404);

        return Storage::disk('local')->download($row->attachment_path, $row->attachment_name, ['Cache-Control' => 'private, no-store']);
    }

    private function addMessage(Enquiry $enquiry, $user, string $body, ?UploadedFile $file): void
    {
        $enquiry->messages()->create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'sender_name' => $user->name,
            'body' => $body,
            'attachment_path' => $file?->store('enquiries/'.$enquiry->id, 'local'),
            'attachment_name' => $file ? mb_substr($file->getClientOriginalName(), 0, 200) : null,
        ]);
    }

    private function tell(Enquiry $enquiry, Company $company, string $title): void
    {
        if (! Modules::notifies('enquiries')) {
            return;
        }
        foreach ($company->users()->where('is_active', true)->pluck('id') as $userId) {
            Notice::create(['company_id' => $company->id, 'user_id' => $userId, 'title' => $title, 'body' => $enquiry->subject, 'url' => route('enquiries.show', ['id' => $enquiry->id])]);
        }
    }
}
