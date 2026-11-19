<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\Company;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $today = now()->toDateString();
        $in30 = now()->addDays(30)->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();
        $yearStart = now()->startOfYear()->toDateString();

        // money that was confirmed, by currency, from a day on
        $received = fn (string $from) => Subscription::whereNotNull('paid_on')->where('paid_on', '>=', $from)
            ->selectRaw('currency, sum(amount) as total')->groupBy('currency')->orderBy('currency')->pluck('total', 'currency')->all();

        $ended = fn ($query) => $query
            ->where(fn ($a) => $a->where('status', Company::ACTIVE)->where('subscription_ends_at', '<', $today))
            ->orWhere(fn ($t) => $t->where('status', Company::TRIAL)->where('trial_ends_at', '<', $today));

        // the last six months, newest first: ['2026-11' => ['USD' => 398.0], ...]
        $months = [];
        for ($i = 0; $i < 6; $i++) {
            $months[now()->startOfMonth()->subMonths($i)->format('Y-m')] = [];
        }
        $since = now()->startOfMonth()->subMonths(5)->toDateString();
        foreach (Subscription::whereNotNull('paid_on')->where('paid_on', '>=', $since)->get(['paid_on', 'currency', 'amount']) as $row) {
            $key = $row->paid_on->format('Y-m');
            if (isset($months[$key])) {
                $months[$key][$row->currency] = ($months[$key][$row->currency] ?? 0) + (float) $row->amount;
            }
        }

        return view('admin.dashboard', [
            'companies' => Company::count(),
            'active' => Company::where('status', Company::ACTIVE)->where(fn ($q) => $q->whereNull('subscription_ends_at')->orWhere('subscription_ends_at', '>=', $today))->count(),
            'trial' => Company::where('status', Company::TRIAL)->where('trial_ends_at', '>=', $today)->count(),
            'endedCount' => Company::where($ended)->count(),
            'suspended' => Company::where('status', Company::SUSPENDED)->count(),
            'newThisMonth' => Company::where('created_at', '>=', now()->startOfMonth())->count(),
            'monthRevenue' => $received($monthStart),
            'yearRevenue' => $received($yearStart),
            'months' => $months,
            'waiting' => Subscription::waiting()->with('company', 'account')->orderBy('id')->get(),
            'payments' => Subscription::with('company', 'account')->whereNotNull('paid_on')->orderByDesc('paid_on')->orderByDesc('id')->limit(8)->get(),
            'expiring' => Company::with('plan')->where('status', Company::ACTIVE)->whereBetween('subscription_ends_at', [$today, $in30])->orderBy('subscription_ends_at')->get(),
            'expired' => Company::with('plan')->where($ended)->orderByDesc('updated_at')->limit(8)->get(),
            'latest' => Company::with('plan')->orderByDesc('id')->limit(8)->get(),
        ]);
    }
}
