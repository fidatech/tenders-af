<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformExpense;
use App\Models\Subscription;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** The platform's own numbers for a year: money received, money spent, profit, and how many trials became paying. */
class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $year = (int) $request->query('year', now()->year);
        if ($year < 2020 || $year > 2100) {
            $year = now()->year;
        }
        $range = ["$year-01-01", "$year-12-31"];

        // [currency => [month => ['in' => .., 'out' => ..]]]
        $months = [];
        foreach (Subscription::whereNotNull('paid_on')->whereBetween('paid_on', $range)->get(['paid_on', 'currency', 'amount']) as $row) {
            $month = (int) $row->paid_on->format('n');
            $months[$row->currency][$month]['in'] = ($months[$row->currency][$month]['in'] ?? 0) + (float) $row->amount;
        }
        foreach (PlatformExpense::whereBetween('date', $range)->get(['date', 'currency', 'amount']) as $row) {
            $month = (int) $row->date->format('n');
            $months[$row->currency][$month]['out'] = ($months[$row->currency][$month]['out'] ?? 0) + (float) $row->amount;
        }
        ksort($months);

        $signups = Company::whereBetween('created_at', ["$year-01-01 00:00:00", "$year-12-31 23:59:59"]);
        $signed = (clone $signups)->count();
        $paying = (clone $signups)->whereHas('subscriptions', fn ($q) => $q->whereNotNull('paid_on'))->count();

        return view('admin.reports', [
            'year' => $year,
            'years' => range(now()->year, now()->year - 5),
            'months' => $months,
            'byCategory' => PlatformExpense::whereBetween('date', $range)->selectRaw('category, currency, sum(amount) as total')
                ->groupBy('category', 'currency')->orderByDesc('total')->get(),
            'byPlan' => Subscription::whereNotNull('paid_on')->whereBetween('paid_on', $range)
                ->leftJoin('plans', 'plans.id', '=', 'subscriptions.plan_id')
                ->selectRaw('plans.name as plan, subscriptions.currency as currency, sum(subscriptions.amount) as total, count(*) as payments')
                ->groupBy('plans.name', 'subscriptions.currency')->orderByDesc('total')->get(),
            'signed' => $signed,
            'paying' => $paying,
            'conversion' => $signed ? (int) round(100 * $paying / $signed) : null,
        ]);
    }
}
