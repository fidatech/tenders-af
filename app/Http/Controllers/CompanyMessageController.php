<?php

namespace App\Http\Controllers;

use App\Models\AdminNotice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompanyMessageController extends Controller
{
    public function create(): View
    {
        return view('company-message');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $sender = $request->user();
        $from = __(':name at :company', ['name' => $sender->name, 'company' => $sender->company->name]);
        $sent = AdminNotice::messageToAll($data['subject'], $from."\n\n".$data['message'], $sender->email);

        if (! $sent) {
            return back()->withInput()->withErrors(['message' => __('Your message could not be sent. Please try again later.')]);
        }

        return redirect()->route('company-message.create')->with('success', __('Your message was sent to the administrators.'));
    }
}
