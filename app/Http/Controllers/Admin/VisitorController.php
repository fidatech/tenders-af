<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tender;
use App\Models\Visit;
use App\Support\GeoIp;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin > Visitors: the visitors of the public website in full (every city, the pages opened and the
 * websites they came from). The public statistics page shows only countries and the larger cities.
 */
class VisitorController extends Controller
{
    public function index(Request $request): View
    {
        $days = in_array((int) $request->query('days'), [7, 30, 90, 365], true) ? (int) $request->query('days') : 30;
        $from = Tender::now()->subDays($days - 1)->toDateString();
        $base = fn () => Visit::where('visited_on', '>=', $from);

        $daily = $base()->selectRaw('visited_on, count(distinct visitor) as v, count(*) as p')->groupBy('visited_on')->orderBy('visited_on')->get();

        return view('admin.visitors', [
            'days' => $days,
            'visitors' => $base()->distinct()->count('visitor'),
            'views' => $base()->count(),
            'daily' => $daily,
            'countries' => $base()->selectRaw('country, count(distinct visitor) as v, count(*) as p')->groupBy('country')->orderByDesc('v')->limit(50)->get()
                ->map(fn ($r) => ['name' => GeoIp::countryName($r->country), 'flag' => GeoIp::flag($r->country), 'v' => (int) $r->v, 'p' => (int) $r->p]),
            'cities' => $base()->whereNotNull('city')->selectRaw('city, region, country, count(distinct visitor) as v')->groupBy('city', 'region', 'country')->orderByDesc('v')->limit(50)->get()
                ->map(fn ($r) => ['name' => $r->city.($r->region && $r->region !== $r->city ? ', '.$r->region : ''), 'country' => GeoIp::countryName($r->country), 'flag' => GeoIp::flag($r->country), 'v' => (int) $r->v]),
            'pages' => $base()->selectRaw('page, count(*) as p, count(distinct visitor) as v')->groupBy('page')->orderByDesc('p')->limit(30)->get(),
            'referrers' => $base()->whereNotNull('referrer')->selectRaw('referrer, count(distinct visitor) as v')->groupBy('referrer')->orderByDesc('v')->limit(20)->get(),
            'devices' => $base()->selectRaw('device, count(distinct visitor) as v')->groupBy('device')->pluck('v', 'device'),
            'located' => GeoIp::available(),
        ]);
    }
}
