<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminNotice;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** The notifications of the administrator who is signed in. */
class NoticeController extends Controller
{
    public function index(): View
    {
        $notices = AdminNotice::where('admin_id', auth('admin')->id());

        return view('admin.notices', [
            'rows' => (clone $notices)->orderByDesc('id')->paginate(30),
            'adminUnread' => $notices->whereNull('read_at')->count(),
        ]);
    }

    /** Open a notification: it is read, and its page opens. */
    public function open(int $id): RedirectResponse
    {
        $notice = AdminNotice::where('admin_id', auth('admin')->id())->findOrFail($id);
        $notice->read_at ??= now();
        $notice->save();

        return $notice->url ? redirect()->to($notice->url) : redirect()->route('admin.notices.index');
    }

    public function read(): RedirectResponse
    {
        AdminNotice::where('admin_id', auth('admin')->id())->whereNull('read_at')->update(['read_at' => now()]);

        return back()->with('success', __('All notifications are read.'));
    }
}
