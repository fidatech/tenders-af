<?php

namespace App\Http\Controllers;

use App\Models\Tender;
use Illuminate\View\View;

/** The first page of a company: the tenders that match it, what closes soon, and how complete its profile is. */
class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $company = company();
        $hasAlerts = $company->industries()->exists() || $company->categories()->exists();
        $matching = fn () => Tender::open()->matching($company);

        return view('dashboard', [
            'company' => $company,
            'hasAlerts' => $hasAlerts,
            'profile' => $company->completeness(),
            'counts' => [
                'open' => Tender::open()->count(),
                'matching' => $hasAlerts ? $matching()->count() : 0,
                'closing' => $hasAlerts ? Tender::closingWithin(7)->matching($company)->count() : Tender::closingWithin(7)->count(),
                'saved' => $company->savedTenders()->count(),
            ],
            // the tenders that fit the company; before it chose its sectors, simply the newest ones
            'tenders' => ($hasAlerts ? $matching() : Tender::open())
                ->with('industry', 'province')->orderByDesc('published_on')->orderByDesc('id')->limit(8)->get(),
        ]);
    }
}
