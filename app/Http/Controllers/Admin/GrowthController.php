<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Product;
use App\Models\Tender;
use App\Models\Visit;
use App\Support\GeoIp;
use App\Support\Modules;
use App\Support\Robots;
use App\Support\Statistics;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Admin > Growth: every figure about the reach and growth of the platform, with real visitors,
 * known robots and unconfirmed traffic kept apart. The public statistics page shows people only.
 */
class GrowthController extends Controller
{
    public const PERIODS = [7, 30, 90, 365];

    public function index(Request $request): View
    {
        $days = in_array((int) $request->query('days'), self::PERIODS, true) ? (int) $request->query('days') : 30;
        $today = Tender::now();
        $from = $today->copy()->subDays($days - 1)->toDateString();
        $in = fn (string $kind) => Visit::where('visited_on', '>=', $from)->where('kind', $kind);

        // day by day: people (once a day each), robot requests, unconfirmed requests
        $rows = Visit::where('visited_on', '>=', $from)
            ->selectRaw('visited_on, kind, count(*) as hits, count(distinct visitor) as people')
            ->groupBy('visited_on', 'kind')->get();
        $daily = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $daily[$today->copy()->subDays($i)->toDateString()] = ['human' => 0, 'bot' => 0, 'pending' => 0];
        }
        foreach ($rows as $row) {
            $day = Carbon::parse($row->visited_on)->toDateString();
            if (isset($daily[$day])) {
                $daily[$day][$row->kind] = $row->kind === 'human' ? (int) $row->people : (int) $row->hits;
            }
        }

        $humanViews = $in('human')->count();
        $botHits = $in('bot')->count();
        $pendingHits = $in('pending')->count();
        $allHits = max(1, $humanViews + $botHits + $pendingHits);

        return view('admin.growth', [
            'days' => $days,
            'daily' => $daily,
            'totals' => [
                'people' => $in('human')->distinct()->count('visitor'),
                'humanViews' => $humanViews,
                'botHits' => $botHits,
                'botNames' => $in('bot')->distinct()->count('bot_name'),
                'pendingHits' => $pendingHits,
                'pendingVisitors' => $in('pending')->distinct()->count('visitor'),
                'botShare' => round(100 * ($botHits + $pendingHits) / $allHits),
            ],
            // people
            'countries' => $in('human')->selectRaw('country, count(distinct visitor) as v, count(*) as p')->groupBy('country')->orderByDesc('v')->limit(40)->get()
                ->map(fn ($r) => ['name' => GeoIp::countryName($r->country), 'flag' => GeoIp::flag($r->country), 'v' => (int) $r->v, 'p' => (int) $r->p]),
            'cities' => $in('human')->whereNotNull('city')->selectRaw('city, region, country, count(distinct visitor) as v')->groupBy('city', 'region', 'country')->orderByDesc('v')->limit(40)->get()
                ->map(fn ($r) => ['name' => $r->city.($r->region && $r->region !== $r->city ? ', '.$r->region : ''), 'flag' => GeoIp::flag($r->country), 'country' => GeoIp::countryName($r->country), 'v' => (int) $r->v]),
            'pages' => $in('human')->selectRaw('page, count(*) as p, count(distinct visitor) as v')->groupBy('page')->orderByDesc('p')->limit(25)->get()
                ->map(fn ($r) => ['page' => $r->page, 'label' => Statistics::pageLabel($r->page), 'p' => (int) $r->p, 'v' => (int) $r->v]),
            'referrers' => $in('human')->whereNotNull('referrer')->selectRaw('referrer, count(distinct visitor) as v')->groupBy('referrer')->orderByDesc('v')->limit(20)->get(),
            'devices' => $in('human')->selectRaw('device, count(distinct visitor) as v')->groupBy('device')->pluck('v', 'device'),
            'sources' => $days <= 90 ? (Statistics::visitors($days)['sources'] ?? []) : [],
            // robots
            'botTypes' => $in('bot')->selectRaw('bot_type, count(*) as hits, count(distinct bot_name) as names')->groupBy('bot_type')->orderByDesc('hits')->get()
                ->map(fn ($r) => ['label' => Robots::typeLabel($r->bot_type), 'hits' => (int) $r->hits, 'names' => (int) $r->names]),
            'bots' => $in('bot')->selectRaw('bot_name, bot_type, count(*) as hits, count(distinct page) as pages, max(created_at) as last_seen')->groupBy('bot_name', 'bot_type')->orderByDesc('hits')->limit(30)->get(),
            'botPages' => $in('bot')->selectRaw('page, count(*) as hits, count(distinct bot_name) as names')->groupBy('page')->orderByDesc('hits')->limit(15)->get()
                ->map(fn ($r) => ['page' => $r->page, 'label' => Statistics::pageLabel($r->page), 'hits' => (int) $r->hits, 'names' => (int) $r->names]),
            'pendingCountries' => $in('pending')->selectRaw('country, count(*) as hits')->groupBy('country')->orderByDesc('hits')->limit(10)->get()
                ->map(fn ($r) => ['name' => GeoIp::countryName($r->country), 'flag' => GeoIp::flag($r->country), 'hits' => (int) $r->hits]),
            // the platform
            'platform' => $this->platform(),
            'located' => GeoIp::available(),
            'since' => Visit::min('visited_on'),
        ]);
    }

    /** New companies, tenders and products in each of the last 12 months, and the totals. */
    private function platform(): array
    {
        $start = Tender::now()->startOfMonth()->subMonths(11);
        $byMonth = fn ($dates) => collect($dates)->countBy(fn ($d) => Carbon::parse($d)->format('Y-m'));
        $companies = $byMonth(Company::where('created_at', '>=', $start)->pluck('created_at'));
        $verified = $byMonth(Company::where('verified_at', '>=', $start)->pluck('verified_at'));
        $tenders = $byMonth(Tender::visible()->where('published_on', '>=', $start->toDateString())->pluck('published_on'));
        $products = Modules::enabled('products') ? $byMonth(Product::where('created_at', '>=', $start)->pluck('created_at')) : collect();

        $months = [];
        for ($i = 0; $i < 12; $i++) {
            $m = $start->copy()->addMonths($i);
            $key = $m->format('Y-m');
            $months[$key] = ['label' => $m->format('M Y'), 'companies' => (int) ($companies[$key] ?? 0), 'verified' => (int) ($verified[$key] ?? 0), 'tenders' => (int) ($tenders[$key] ?? 0), 'products' => (int) ($products[$key] ?? 0)];
        }

        return [
            'months' => $months,
            'withProducts' => Modules::enabled('products'),
            'totals' => [
                'Companies' => Company::where('status', '!=', Company::SUSPENDED)->count(),
                'Verified companies' => Company::where('verify_status', 'verified')->count(),
                'Tenders published' => Tender::visible()->count(),
                'Open tenders' => Tender::open()->count(),
            ] + (Modules::enabled('products') ? ['Products live' => Product::live()->count()] : []),
        ];
    }
}
