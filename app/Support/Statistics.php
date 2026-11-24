<?php

namespace App\Support;

use App\Models\Company;
use App\Models\Industry;
use App\Models\Product;
use App\Models\Province;
use App\Models\Tender;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Every figure of the statistics page (and of its CSV download), counted from the database.
 * Nothing is estimated. A section is shown only when it has something to show; zeros are left out.
 */
class Statistics
{
    /** Visitor figures are counted at most once in this many seconds (they read the most rows). */
    public const VISITOR_CACHE = 600;

    /** A city is named only from this many visitors on, so a single person is never pointed at. */
    public const CITY_MIN = 3;

    /** The headline figures: [key => [label, number, explanation]], zeros left out. */
    public static function headline(): array
    {
        $now = Tender::now();
        $figures = [
            'open' => ['Open tenders', Tender::open()->count(), 'Tenders whose deadline has not passed.'],
            'month' => ['Published this month', Tender::visible()->where('published_on', '>=', $now->copy()->startOfMonth()->toDateString())->count(), 'Tenders published since the 1st of '.$now->format('F').'.'],
            'closing' => ['Closing this week', Tender::closingWithin(7)->count(), 'Open tenders whose deadline is within seven days.'],
            'published' => ['Tenders published', Tender::visible()->count(), 'Every tender published on the site, open or closed.'],
            'buyers' => ['Buyers', Tender::visible()->distinct()->count('buyer_name'), 'Organisations whose tenders are on the site.'],
            'awarded' => ['Tenders awarded', Tender::where('status', Tender::AWARDED)->count(), 'Tenders whose winner was published.'],
            'companies' => ['Registered companies', Company::where('status', '!=', Company::SUSPENDED)->count(), 'Companies and organisations with an account.'],
            'verified' => ['Verified companies', Company::listed()->where('verify_status', 'verified')->count(), 'Companies whose business licence we checked.'],
            'products' => Modules::enabled('products') ? ['Products listed', Product::live()->count(), 'Wholesale products from factories and producers.'] : null,
        ];
        $min = max(1, (int) Platform::get('stats_min'));

        return array_filter($figures, fn ($figure) => $figure && $figure[1] >= $min);
    }

    /** Tenders published in each of the last 12 months: ['2026-11' => ['Nov 2026', 4], ...]. */
    public static function tendersByMonth(): array
    {
        $start = Tender::now()->startOfMonth()->subMonths(11);
        $counts = Tender::visible()->where('published_on', '>=', $start->toDateString())->pluck('published_on')
            ->countBy(fn ($day) => Carbon::parse($day)->format('Y-m'));
        $months = [];
        for ($i = 0; $i < 12; $i++) {
            $month = $start->copy()->addMonths($i);
            $months[$month->format('Y-m')] = [$month->format('M Y'), (int) ($counts[$month->format('Y-m')] ?? 0)];
        }

        return array_sum(array_column($months, 1)) > 0 ? $months : [];
    }

    /** Sectors with tenders: name, slug, open, all; the busiest first. */
    public static function sectors(): Collection
    {
        return Industry::where('is_active', true)
            ->withCount(['tenders as all_count' => fn (Builder $q) => $q->visible(), 'tenders as open_count' => fn (Builder $q) => $q->open()])
            ->orderByDesc('all_count')->orderBy('name')->get()
            ->filter(fn ($sector) => $sector->all_count > 0)->values();
    }

    /** Every province with its open and all tenders, companies and products; the busiest first. */
    public static function provinces(array $open): Collection
    {
        $all = Tender::visible()->whereNotNull('province_id')->selectRaw('province_id, count(*) as n')->groupBy('province_id')->pluck('n', 'province_id');
        $companies = Company::listed()->whereNotNull('province_id')->selectRaw('province_id, count(*) as n')->groupBy('province_id')->pluck('n', 'province_id');
        $made = Modules::enabled('products') ? Product::live()->whereNotNull('province_id')->selectRaw('province_id, count(*) as n')->groupBy('province_id')->pluck('n', 'province_id') : collect();

        return Province::orderBy('name')->get()->map(fn (Province $p) => [
            'name' => $p->name, 'slug' => $p->slug, 'open' => (int) ($open[$p->id] ?? 0), 'all' => (int) ($all[$p->id] ?? 0),
            'companies' => (int) ($companies[$p->id] ?? 0), 'products' => (int) ($made[$p->id] ?? 0),
        ])->sortBy([['all', 'desc'], ['companies', 'desc'], ['name', 'asc']])->values();
    }

    /** The organisations that published the most tenders: [name => count]. */
    public static function topBuyers(int $limit = 10): array
    {
        return Tender::visible()->selectRaw('buyer_name, count(*) as n')->groupBy('buyer_name')
            ->orderByDesc('n')->orderBy('buyer_name')->limit($limit)->pluck('n', 'buyer_name')->map(fn ($n) => (int) $n)->all();
    }

    /** Tenders by kind of buyer and by kind of tender: [label => count], zeros left out. */
    public static function byKind(string $column, string $config): array
    {
        $labels = (array) config($config);
        $counts = Tender::visible()->selectRaw("$column as k, count(*) as n")->groupBy($column)->pluck('n', 'k');
        $out = [];
        foreach ($counts as $key => $n) {
            $label = $labels[$key] ?? $key;
            $out[is_array($label) ? ($label[1] ?? $key) : $label] = (int) $n; // tender types are [short, full]
        }
        arsort($out);

        return $out;
    }

    /** Deadlines: open tenders closing within 7, 14 and 30 days, and how long tenders usually stay open. */
    public static function deadlines(): array
    {
        $windows = ['7 days' => Tender::closingWithin(7)->count(), '14 days' => Tender::closingWithin(14)->count(), '30 days' => Tender::closingWithin(30)->count()];
        $days = Tender::visible()->whereNotNull('published_on')->whereNotNull('deadline')
            ->where('published_on', '>=', Tender::now()->subYear()->toDateString())->get(['published_on', 'deadline'])
            ->map(fn (Tender $t) => max(0, (int) Carbon::parse($t->published_on)->diffInDays($t->deadline)))->sort()->values();
        $median = $days->isEmpty() ? null : (int) $days[intdiv($days->count(), 2)];

        return ['windows' => array_sum($windows) > 0 ? $windows : [], 'median' => $median, 'sample' => $days->count()];
    }

    /** Companies by kind of business ([label => count]) and how many are verified. */
    public static function companies(): array
    {
        $listed = Company::listed()->get(['id', 'business_types', 'verify_status']);
        $types = [];
        foreach ($listed as $company) {
            foreach ((array) $company->business_types as $type) {
                $label = Company::BUSINESS_TYPES[$type] ?? null;
                if ($label) {
                    $types[$label] = ($types[$label] ?? 0) + 1;
                }
            }
        }
        arsort($types);

        return ['listed' => $listed->count(), 'verified' => $listed->where('verify_status', 'verified')->count(), 'types' => $types];
    }

    /** Periods a visitor may choose on the statistics page, in days. */
    public const PERIODS = [7, 30, 90];

    /** Where visitors came from, by the website that sent them. */
    private const SOURCES = [
        'Search engines' => '/(^|\.)(google|bing|yahoo|duckduckgo|yandex|baidu|ecosia|qwant|brave|startpage)\./i',
        'Social media' => '/(^|\.)(facebook|fb|instagram|linkedin|lnkd|twitter|x|t|youtube|telegram|whatsapp|reddit|tiktok|pinterest)\.(com|co|me|org|in)$/i',
    ];

    /**
     * The visitors of the last $days days: totals and the change against the $days before, visitors and
     * pages by day, by country, by city, by device, by source, and the pages opened most.
     * Counted at most every ten minutes.
     */
    public static function visitors(int $days = 30): array
    {
        return Cache::remember('stats.visitors.'.$days, self::VISITOR_CACHE, function () use ($days) {
            $today = Tender::now();
            $from = $today->copy()->subDays($days - 1)->toDateString();
            // people only: robots, crawlers and unconfirmed traffic are on Admin > Growth
            $base = fn () => Visit::where('visited_on', '>=', $from)->where('kind', 'human');

            $views = $base()->count();
            $since = Visit::where('kind', 'human')->min('visited_on');
            if ($views === 0) {
                return ['views' => 0, 'since' => $since];
            }
            $visitors = $base()->distinct()->count('visitor');

            // the same number of days just before, to show the change (only once counting covered them)
            $before = null;
            $prevFrom = $today->copy()->subDays(2 * $days - 1)->toDateString();
            if ($since && $since <= $prevFrom) {
                $before = Visit::where('kind', 'human')->whereBetween('visited_on', [$prevFrom, $today->copy()->subDays($days)->toDateString()])->distinct()->count('visitor');
            }

            // one visitor per person per day: the visitor code itself changes every day
            $perDay = $base()->selectRaw('visited_on, count(distinct visitor) as v, count(*) as p')->groupBy('visited_on')->get()
                ->mapWithKeys(fn ($row) => [Carbon::parse($row->visited_on)->toDateString() => [(int) $row->v, (int) $row->p]]);
            $daily = [];
            for ($i = $days - 1; $i >= 0; $i--) {
                $day = $today->copy()->subDays($i);
                $daily[$day->toDateString()] = [$day->format('j M'), $perDay[$day->toDateString()][0] ?? 0, $perDay[$day->toDateString()][1] ?? 0];
            }

            $countries = $base()->selectRaw('country, count(distinct visitor) as v')->groupBy('country')->orderByDesc('v')->get()
                ->map(fn ($row) => ['code' => $row->country, 'name' => GeoIp::countryName($row->country), 'flag' => GeoIp::flag($row->country), 'visitors' => (int) $row->v]);
            $cities = $base()->whereNotNull('city')->selectRaw('city, region, country, count(distinct visitor) as v')
                ->groupBy('city', 'region', 'country')->orderByDesc('v')->limit(15)->get()
                ->filter(fn ($row) => (int) $row->v >= self::CITY_MIN)
                ->map(fn ($row) => ['city' => $row->city, 'region' => $row->region, 'country' => GeoIp::countryName($row->country), 'flag' => GeoIp::flag($row->country), 'visitors' => (int) $row->v])->values();
            $devices = $base()->selectRaw('device, count(distinct visitor) as v')->groupBy('device')->pluck('v', 'device')->map(fn ($v) => (int) $v)->all();

            // sources: only kinds are shown publicly, never the other websites' names
            $sources = ['Direct or bookmarks' => 0, 'Search engines' => 0, 'Social media' => 0, 'Other websites' => 0];
            // each visitor once, by the first page they opened that day: later pages were reached by clicking inside the site
            $firstViews = Visit::selectRaw('min(id)')->where('visited_on', '>=', $from)->where('kind', 'human')->groupBy('visitor');
            foreach (Visit::whereIn('id', $firstViews)->selectRaw('referrer, count(*) as v')->groupBy('referrer')->get() as $row) {
                $kind = 'Other websites';
                if (! $row->referrer) {
                    $kind = 'Direct or bookmarks';
                } else {
                    foreach (self::SOURCES as $name => $pattern) {
                        if (preg_match($pattern, $row->referrer)) {
                            $kind = $name;
                            break;
                        }
                    }
                }
                $sources[$kind] += (int) $row->v;
            }

            $pages = $base()->selectRaw('page, count(*) as p')->groupBy('page')->orderByDesc('p')->limit(10)->get()
                ->map(fn ($row) => ['page' => $row->page, 'label' => self::pageLabel($row->page), 'views' => (int) $row->p]);

            return [
                'days' => $days,
                'views' => $views,
                'visitors' => $visitors,
                'before' => $before,
                'daily' => $daily,
                'countries' => $countries->values()->all(),
                'cities' => $cities->all(),
                'devices' => $devices,
                'sources' => array_filter($sources),
                'pages' => $pages->all(),
                'since' => $since,
            ];
        });
    }

    /** A readable name for a page of the site: "/tender/road-in-herat" → "Tender: road in herat". */
    public static function pageLabel(string $path): string
    {
        $fixed = ['/sitemap.xml' => 'Sitemap', '/robots.txt' => 'robots.txt', '/llms.txt' => 'llms.txt', '/' => 'Home page', '/tenders' => 'Open tenders', '/companies' => 'Company directory', '/sectors' => 'Sectors', '/provinces' => 'Provinces',
            '/statistics' => 'Statistics', '/pricing' => 'Pricing', '/how-it-works' => 'How it works', '/questions' => 'Questions', '/contact' => 'Contact',
            '/login' => 'Sign in', '/register' => 'Register', '/products' => 'Products', '/privacy' => 'Privacy', '/terms' => 'Terms'];
        if (isset($fixed[$path])) {
            return $fixed[$path];
        }
        $parts = explode('/', trim($path, '/'));
        $kind = ['tender' => 'Tender', 'companies' => 'Company', 'products' => 'Product'][$parts[0]] ?? null;
        if ($parts[0] === 'tenders' && in_array($parts[1] ?? '', ['sector', 'province'], true)) {
            $kind = ucfirst($parts[1]);
        }
        $name = str_replace('-', ' ', (string) end($parts));

        return $kind && count($parts) > 1 ? $kind.': '.$name : $path;
    }
}
