<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminLog;
use App\Models\Enquiry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin > Reported enquiries. Threads between companies are private: an administrator sees a
 * thread only after one of the two companies reports it, and the reading is logged.
 */
class EnquiryController extends Controller
{
    public function index(Request $request): View
    {
        $open = $request->query('state') !== 'done';
        $rows = Enquiry::whereNotNull('reported_at')->with('sender:id,name', 'receiver:id,name')
            ->when($open, fn ($q) => $q->where('status', 'open'), fn ($q) => $q->where('status', 'closed'))
            ->orderByDesc('reported_at')->paginate(25)->withQueryString();

        return view('admin.enquiries.index', ['rows' => $rows, 'open' => $open]);
    }

    public function show(int $id): View
    {
        $enquiry = Enquiry::whereNotNull('reported_at')->with('messages', 'sender', 'receiver')->findOrFail($id);
        AdminLog::record('read a reported enquiry', $enquiry->sender?->name.' → '.$enquiry->receiver?->name, $enquiry->subject);

        return view('admin.enquiries.show', ['enquiry' => $enquiry]);
    }

    /** Close the thread so nobody can write in it any more. */
    public function close(int $id): RedirectResponse
    {
        $enquiry = Enquiry::whereNotNull('reported_at')->findOrFail($id);
        $enquiry->update(['status' => 'closed']);
        AdminLog::record('closed a reported enquiry', $enquiry->subject);

        return redirect()->route('admin.enquiries.index')->with('success', __('The thread is closed.'));
    }
}
