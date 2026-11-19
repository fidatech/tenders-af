<?php

namespace App\Http\Controllers;

use App\Mail\ContactMessage;
use App\Models\Admin;
use App\Models\Company;
use App\Models\Industry;
use App\Models\Plan;
use App\Models\PlatformAccount;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Province;
use App\Models\Tender;
use App\Support\Platform;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * The public website: tenders (as summaries), sectors, provinces, the company directory and
 * the pages about the site. What the site says about itself is written in config/site.php;
 * prices and the ways to pay come from the live settings, so the pages, the structured data
 * for search engines and /llms.txt always say the same.
 */
class SiteController extends Controller
{
    /** The fixed pages of the site: route name => [address, title in menus]. Also the sitemap. */
    public const PAGES = [
        'site.home' => ['/', 'Home'],
        'site.tenders' => ['/tenders', 'Tenders'],
        'site.sectors' => ['/sectors', 'Sectors'],
        'site.provinces' => ['/provinces', 'Provinces'],
        'site.companies' => ['/companies', 'Companies'],
        'site.statistics' => ['/statistics', 'Statistics'],
        'site.pricing' => ['/pricing', 'Pricing'],
        'site.how' => ['/how-it-works', 'How it works'],
        'site.faq' => ['/questions', 'Questions'],
        'site.contact' => ['/contact', 'Contact'],
        'site.privacy' => ['/privacy', 'Privacy'],
        'site.terms' => ['/terms', 'Terms'],
    ];

    public function home(): View
    {
        $counts = $this->provinceCounts();

        return $this->page('site.home', [
            'title' => 'Tenders in Afghanistan: every tender in one place',
            'description' => config('site.description'),
            'stats' => [
                'open' => Tender::open()->count(),
                'closing' => Tender::closingWithin(7)->count(),
                'today' => Tender::visible()->where('published_on', Tender::now()->toDateString())->count(),
                'buyers' => Tender::visible()->distinct()->count('buyer_name'),
                'companies' => Company::listed()->where('verify_status', 'verified')->count(),
            ],
            'latest' => Tender::open()->with('industry', 'province')->orderByDesc('published_on')->orderByDesc('id')->limit(6)->get(),
            'sectors' => $this->sectorList()->take(8),
            'provinceOptions' => Province::options(),
            'map' => $this->map($counts),
            'top' => Province::whereIn('id', array_keys($counts))->get()->sortByDesc(fn (Province $p) => $counts[$p->id])->take(4)->values()
                ->map(fn (Province $p) => ['name' => $p->name, 'slug' => $p->slug, 'count' => $counts[$p->id]]),
            'sponsored' => Platform::bool('products_enabled') ? Product::live()->sponsored()->with('company', 'images', 'province')->inRandomOrder()->limit(4)->get() : collect(),
            'featured' => Company::listed()->where('verify_status', 'verified')->with('province', 'industries')->orderByDesc('verified_at')->limit(4)->get(),
            'faqSchema' => array_slice($this->faqs(), 0, 4),
        ]);
    }

    /** Every tender, with the search box and the pick lists. */
    public function tenders(Request $request): View
    {
        return $this->tenderList($request, [
            'title' => 'Tenders in Afghanistan',
            'heading' => 'Tenders in Afghanistan',
            'lead' => 'Open tenders from NGOs, UN agencies, government and private buyers. Search by word, or narrow the list by sector, province and kind of tender.',
            'description' => 'The list of open tenders in Afghanistan: RFQs, RFPs, ITBs and more from NGOs, UN agencies, government and private buyers, by sector and province.',
            'crumbs' => [['Tenders', null]],
        ]);
    }

    public function sector(Request $request, string $slug): View
    {
        $industry = Industry::where('slug', $slug)->where('is_active', true)->firstOrFail();

        return $this->tenderList($request, [
            'title' => $industry->name.' tenders in Afghanistan',
            'heading' => $industry->name.' tenders in Afghanistan',
            'lead' => 'Open tenders in the '.$industry->name.' sector, from every kind of buyer and every province. Register your company for free to get new ones by email and WhatsApp.',
            'description' => 'Open '.$industry->name.' tenders in Afghanistan from NGOs, UN agencies, government and private buyers, with deadlines and buyers.',
            'crumbs' => [['Tenders', route('site.tenders')], ['Sectors', route('site.sectors')], [$industry->name, null]],
            'fixed' => ['industry' => $industry->id],
            'categories' => $industry->categories()->where('is_active', true)->orderBy('name')->pluck('name')->all(),
        ]);
    }

    public function province(Request $request, string $slug): View
    {
        $province = Province::where('slug', $slug)->firstOrFail();

        return $this->tenderList($request, [
            'title' => 'Tenders in '.$province->name.' province',
            'heading' => 'Tenders in '.$province->name,
            'lead' => 'Open tenders in '.$province->name.' province'.($province->capital && $province->capital !== $province->name ? ', including '.$province->capital : '').'. Tenders that cover several provinces are in the full list.',
            'description' => 'Open tenders in '.$province->name.' province, Afghanistan, from NGOs, UN agencies, government and private buyers, with deadlines.',
            'crumbs' => [['Tenders', route('site.tenders')], ['Provinces', route('site.provinces')], [$province->name, null]],
            'fixed' => ['province' => $province->id],
        ]);
    }

    private function tenderList(Request $request, array $meta): View
    {
        $fixed = $meta['fixed'] ?? [];
        $filters = $fixed + $request->only('q', 'industry', 'province', 'type', 'buyer');
        $all = $request->boolean('closed');

        $rows = Tender::visible()->filtered($filters)->when(! $all, fn (Builder $q) => $q->open())
            ->with('industry', 'province')
            ->orderByRaw('case when deadline is null then 1 else 0 end')->orderBy($all ? 'published_on' : 'deadline', $all ? 'desc' : 'asc')->orderByDesc('id')
            ->paginate(18)->withQueryString();

        return $this->page('site.tenders', $meta + [
            'rows' => $rows,
            'filters' => $filters,
            'fixed' => $fixed,
            'all' => $all,
            'industries' => Industry::options(),
            'provinceOptions' => Province::options(),
            'categories' => $meta['categories'] ?? [],
            // a list narrowed by a search word or a pick list is a variant of the page: search engines keep the plain one
            'noindex' => $request->hasAny(['q', 'type', 'buyer', 'closed', 'page']) || (! $fixed && $request->hasAny(['industry', 'province'])),
        ]);
    }

    /** One tender as everybody may read it: the summary, the facts and the deadline. */
    public function tender(string $slug): View
    {
        $tender = Tender::visible()->with('industry', 'category', 'province', 'documents', 'company')->where('slug', $slug)->firstOrFail();
        $tender->increment('views');
        $place = $tender->placeText();

        $faqs = array_values(array_filter([
            ['Who is the buyer of this tender?', $tender->buyer_name.' ('.$tender->buyerTypeLabel().').'],
            $tender->deadline ? ['When is the deadline?', 'Bids close on '.fmt_datetime($tender->deadline).' Kabul time.'] : null,
            ['Where is the work or the delivery?', $place.($tender->province && $tender->location ? ', '.$tender->location : '').'.'],
            $tender->submissionLabel() ? ['How are bids handed in?', $tender->submissionLabel().'. The address and the required forms are in the full details of the tender.'] : null,
            ['How do I see the full tender and its documents?', 'Register your company for free and choose a paid plan. The full details and the documents of every tender open on a paid plan.'],
        ]));

        return $this->page('site.tender', [
            'title' => $tender->title.' ('.$tender->typeLabel().', '.$place.')',
            'description' => mb_substr($tender->typeName().' from '.$tender->buyer_name.' in '.$place.'. '.preg_replace('/\s+/', ' ', $tender->summary), 0, 300),
            'tender' => $tender,
            'faqSchema' => $faqs,
            'tenderFaqs' => $faqs,
            'similar' => Tender::open()->where('industry_id', $tender->industry_id)->whereKeyNot($tender->id)->with('province')->orderBy('deadline')->limit(4)->get(),
            'crumbs' => array_values(array_filter([
                ['Tenders', route('site.tenders')],
                $tender->industry ? [$tender->industry->name, route('site.sector', ['slug' => $tender->industry->slug])] : null,
                $tender->province ? [$tender->province->name, route('site.province', ['slug' => $tender->province->slug])] : null,
                [$tender->number(), null],
            ])),
            // a closed tender stays online with its result; a cancelled one is kept out of search results
            'noindex' => $tender->status === Tender::CANCELLED,
        ]);
    }

    public function sectors(): View
    {
        return $this->page('site.sectors', [
            'title' => 'Tenders by sector',
            'description' => 'Tenders in Afghanistan by sector: construction, health, ICT, agriculture, food and relief items, transport, energy, consultancy and more.',
            'sectors' => $this->sectorList(),
        ]);
    }

    public function provinces(): View
    {
        $counts = $this->provinceCounts();

        return $this->page('site.provinces', [
            'title' => 'Tenders by province',
            'description' => 'Open tenders in each of the 34 provinces of Afghanistan, on a map and as a list.',
            'map' => $this->map($counts),
            'list' => Province::orderBy('name')->get()->map(fn (Province $p) => ['name' => $p->name, 'slug' => $p->slug, 'count' => $counts[$p->id] ?? 0]),
        ]);
    }

    /** The free company directory. */
    public function companies(Request $request): View
    {
        $term = trim((string) $request->query('q', ''));
        $rows = Company::listed()
            ->when($term !== '', fn (Builder $q) => $q->where(fn (Builder $inner) => $inner->where('name', 'like', "%$term%")->orWhere('about', 'like', "%$term%")))
            ->when($request->query('industry'), fn (Builder $q, $id) => $q->whereHas('industries', fn (Builder $i) => $i->whereKey($id)))
            ->when($request->query('province'), fn (Builder $q, $id) => $q->where('province_id', $id))
            ->when($request->boolean('verified'), fn (Builder $q) => $q->where('verify_status', 'verified'))
            ->with('province', 'industries')
            ->orderByRaw("case when verify_status = 'verified' then 0 else 1 end")->orderBy('name')
            ->paginate(24)->withQueryString();

        return $this->page('site.companies', [
            'title' => 'Companies in Afghanistan: free business directory',
            'description' => 'A free directory of companies in Afghanistan by sector and province, with verified suppliers for NGOs, UN agencies, government and private buyers.',
            'rows' => $rows,
            'term' => $term,
            'industries' => Industry::options(),
            'provinceOptions' => Province::options(),
            'noindex' => $request->hasAny(['q', 'industry', 'province', 'verified', 'page']),
        ]);
    }

    public function company(string $slug): View
    {
        $company = Company::listed()->with('province', 'industries', 'categories')->where('slug', $slug)->firstOrFail();
        $place = $company->province?->name ?: $company->country;

        return $this->page('site.company', [
            'title' => $company->name.($place ? ', '.$place : ''),
            'description' => mb_substr($company->about ? preg_replace('/\s+/', ' ', $company->about) : $company->name.' is a '.mb_strtolower($company->kindLabel()).($place ? ' in '.$place : '').' registered on '.config('app.name').'.', 0, 300),
            'company' => $company,
            // what this organisation posted itself: open ones first, then the newest results
            'products' => Platform::bool('products_enabled') ? Product::live()->where('company_id', $company->id)->with('company', 'images', 'province')->sponsoredFirst()->limit(6)->get() : collect(),
            'posted' => Tender::visible()->where('company_id', $company->id)->with('industry', 'province')
                ->orderByRaw("case when status = 'published' then 0 else 1 end")->orderByDesc('published_on')->orderByDesc('id')->limit(12)->get(),
            'crumbs' => [['Companies', route('site.companies')], [$company->name, null]],
        ]);
    }

    /** The logo of a listed company. */
    /* ------------------------------------------------------------ products for wholesale */

    public function products(Request $request): View
    {
        abort_unless(Platform::bool('products_enabled'), 404);
        $term = trim((string) $request->query('q', ''));
        $rows = Product::live()
            ->when($term !== '', fn (Builder $q) => $q->where(fn (Builder $inner) => $inner->where('name', 'like', "%$term%")->orWhere('summary', 'like', "%$term%")))
            ->when($request->query('industry'), fn (Builder $q, $id) => $q->where('industry_id', $id))
            ->when($request->query('province'), fn (Builder $q, $id) => $q->where('province_id', $id))
            ->with('company', 'industry', 'province', 'images')->sponsoredFirst()->paginate(24)->withQueryString();

        return $this->page('site.products', [
            'title' => 'Wholesale products made and supplied in Afghanistan',
            'description' => 'Products from factories and producers in Afghanistan, for wholesale: unit, smallest order and a direct contact for NGOs, UN agencies, government offices and traders.',
            'rows' => $rows,
            'term' => $term,
            'industries' => Industry::options(),
            'provinceOptions' => Province::options(),
            'crumbs' => [['Products', null]],
            'noindex' => $request->hasAny(['q', 'industry', 'province', 'page']),
        ]);
    }

    public function product(string $slug): View
    {
        abort_unless(Platform::bool('products_enabled'), 404);
        $product = Product::live()->with('company.province', 'industry', 'province', 'images')->where('slug', $slug)->firstOrFail();
        $product->increment('views');
        $place = $product->province?->name ?: ($product->company->province?->name ?: 'Afghanistan');

        return $this->page('site.product', [
            'title' => $product->name.' wholesale, '.$place.' ('.$product->company->name.')',
            'description' => mb_substr($product->summary.' Smallest order: '.$product->min_order.'. '.$product->priceText().'.', 0, 300),
            'product' => $product,
            'place' => $place,
            'more' => Product::live()->where('company_id', $product->company_id)->whereKeyNot($product->id)->with('company', 'images', 'province')->sponsoredFirst()->limit(3)->get(),
            'crumbs' => array_values(array_filter([
                ['Products', route('site.products')],
                $product->industry ? [$product->industry->name, route('site.products', ['industry' => $product->industry_id])] : null,
                [$product->name, null],
            ])),
        ]);
    }

    /** A visitor presses "WhatsApp": the click is counted for the company, then WhatsApp opens. */
    public function productContact(string $slug): RedirectResponse
    {
        abort_unless(Platform::bool('products_enabled'), 404);
        $product = Product::live()->with('company')->where('slug', $slug)->firstOrFail();
        $number = preg_replace('/\D+/', '', (string) ($product->company->whatsapp ?: $product->company->phone));
        abort_if($number === '', 404);
        $product->increment('contacts');
        // 0700 123 456 is written the international way: 93700123456
        $number = str_starts_with($number, '00') ? substr($number, 2) : (str_starts_with($number, '0') ? '93'.substr($number, 1) : $number);

        return redirect()->away('https://wa.me/'.$number.'?text='.rawurlencode('Hello, I saw "'.$product->name.'" on '.config('app.name').' ('.route('site.products.show', ['slug' => $product->slug]).'). Please send me your wholesale offer.'));
    }

    /** A picture of a product. Before it is published only its company and the administrators see it. */
    public function productImage(int $id): StreamedResponse
    {
        $image = ProductImage::with('product')->findOrFail($id);
        $this->maySee($image->product);
        abort_unless(Storage::disk('local')->exists($image->path), 404);

        return Storage::disk('local')->response($image->path, null, ['Cache-Control' => $image->product->isLive() ? 'public, max-age=86400' : 'private, no-store']);
    }

    public function productVideo(int $id): BinaryFileResponse
    {
        $product = Product::findOrFail($id);
        $this->maySee($product);
        abort_unless($product->video_path && Storage::disk('local')->exists($product->video_path), 404);

        // a file response answers "Range" requests, which a video player needs to seek
        return response()->file(Storage::disk('local')->path($product->video_path));
    }

    private function maySee(Product $product): void
    {
        $open = $product->isLive() && Platform::bool('products_enabled');
        abort_unless($open || auth('admin')->check() || auth('web')->user()?->company_id === $product->company_id, 404);
    }

    /* ---------------------------------------------------------------------- statistics */

    /** Real numbers about the site, for visitors, journalists and AI assistants. Small figures stay hidden until they grow. */
    public function statistics(): View
    {
        $min = (int) platform('stats_min');
        $products = Platform::bool('products_enabled');
        // [label, number, explanation]
        $figures = [
            ['Tenders published', Tender::visible()->count(), 'Every tender published on the site so far, open or closed.'],
            ['Open tenders', Tender::open()->count(), 'Tenders whose deadline has not passed.'],
            ['Closing this week', Tender::closingWithin(7)->count(), 'Open tenders whose deadline is within seven days.'],
            ['Tenders awarded', Tender::where('status', Tender::AWARDED)->count(), 'Tenders whose winner was published.'],
            ['Buyers', Tender::visible()->distinct()->count('buyer_name'), 'Organisations whose tenders are on the site.'],
            ['Registered companies', Company::where('status', '!=', Company::SUSPENDED)->count(), 'Companies and organisations with an account.'],
            ['Verified companies', Company::listed()->where('verify_status', 'verified')->count(), 'Companies whose business licence we checked.'],
            $products ? ['Products listed', Product::live()->count(), 'Wholesale products from factories and producers.'] : null,
        ];
        $figures = array_values(array_filter($figures, fn ($figure) => $figure && $figure[1] >= max(1, $min)));

        $open = $this->provinceCounts();
        $all = Tender::visible()->whereNotNull('province_id')->selectRaw('province_id, count(*) as n')->groupBy('province_id')->pluck('n', 'province_id');
        $companies = Company::listed()->whereNotNull('province_id')->selectRaw('province_id, count(*) as n')->groupBy('province_id')->pluck('n', 'province_id');
        $made = $products ? Product::live()->whereNotNull('province_id')->selectRaw('province_id, count(*) as n')->groupBy('province_id')->pluck('n', 'province_id') : collect();

        return $this->page('site.statistics', [
            'title' => 'Statistics: tenders and companies in Afghanistan, by province and sector',
            'description' => 'Live figures from '.config('app.name').': tenders published and open, buyers, registered and verified companies, by province and by sector.',
            'figures' => $figures,
            'showTables' => Tender::visible()->count() >= max(1, $min),
            'withProducts' => $products,
            'map' => $this->map($open),
            'provinces' => Province::orderBy('name')->get()->map(fn (Province $p) => [
                'name' => $p->name, 'slug' => $p->slug, 'open' => (int) ($open[$p->id] ?? 0), 'all' => (int) ($all[$p->id] ?? 0),
                'companies' => (int) ($companies[$p->id] ?? 0), 'products' => (int) ($made[$p->id] ?? 0),
            ])->sortByDesc('all')->values(),
            'sectors' => Industry::where('is_active', true)
                ->withCount(['tenders as all_count' => fn (Builder $q) => $q->visible(), 'tenders as open_count' => fn (Builder $q) => $q->open()])
                ->orderByDesc('all_count')->orderBy('name')->get(),
            'updated' => fmt_datetime(Tender::now()),
            'crumbs' => [['Statistics', null]],
        ]);
    }

    public function logo(string $slug): StreamedResponse
    {
        $company = Company::listed()->where('slug', $slug)->firstOrFail();
        abort_unless($company->logo_path && Storage::disk('local')->exists($company->logo_path), 404);

        return Storage::disk('local')->response($company->logo_path, null, ['Cache-Control' => 'public, max-age=86400']);
    }

    public function pricing(Request $request): View
    {
        return $this->page('site.pricing', [
            'title' => 'Pricing: free registration, plans in AFN and USD',
            'description' => 'Registering a company on '.config('app.name').' is free. Paid plans open the full tenders and the alerts, priced in AFN for Afghan companies and in USD for NGOs and international clients.',
            'currency' => strtoupper((string) $request->query('currency')) === 'USD' ? 'USD' : 'AFN',
            'faqSchema' => array_values(array_filter($this->faqs(), fn (array $faq) => preg_match('/cost|pay|refund|free|AFN/i', $faq[0]) === 1)),
        ]);
    }

    public function how(): View
    {
        return $this->page('site.how', [
            'title' => 'How it works',
            'description' => 'How '.config('app.name').' works for companies and for buyers: free registration, tender alerts by email and WhatsApp, full tenders on a paid plan.',
        ]);
    }

    public function faq(): View
    {
        return $this->page('site.faq', [
            'title' => 'Questions and answers',
            'description' => 'Answers about '.config('app.name').': what it is, free registration, prices in AFN and USD, payment, alerts, verification and bidding.',
            'faqSchema' => $this->faqs(),
        ]);
    }

    public function contact(): View
    {
        return $this->page('site.contact', [
            'title' => 'Contact, and post a tender',
            'description' => 'Write to the people behind '.config('app.name').', or send us a tender to publish for free.',
        ]);
    }

    public function privacy(): View
    {
        return $this->page('site.privacy', ['title' => 'Privacy', 'description' => 'What '.config('app.name').' keeps about you and your company, and what it does with it.']);
    }

    public function terms(): View
    {
        return $this->page('site.terms', ['title' => 'Terms of use', 'description' => 'The rules for using '.config('app.name').', including tenders, company profiles, payments and refunds.']);
    }

    /** A message from the contact form, emailed to the support address (or to the administrators). */
    public function send(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        // a box people never see: only programs fill it in, and their message is dropped quietly
        if (! $request->filled('website')) {
            $to = array_values(array_filter([Platform::get('support_email')])) ?: Admin::where('is_active', true)->pluck('email')->all();
            if ($to) {
                try {
                    Mail::to($to)->send(new ContactMessage($data['name'], $data['email'], $data['message']));
                } catch (Throwable $e) {
                    report($e);
                }
            }
        }

        return redirect()->route('site.contact')->with('sent', true);
    }

    /** For search engines: which addresses exist. Fixed pages, sectors, provinces, tenders and companies. */
    public function sitemap(): Response
    {
        $today = date('Y-m-d');
        $urls = [];
        foreach (self::PAGES as $name => [$path]) {
            $urls[] = [url($path), $today, $path === '/' ? '1.0' : (in_array($name, ['site.privacy', 'site.terms'], true) ? '0.3' : '0.8')];
        }
        try {
            foreach (Industry::where('is_active', true)->get(['slug', 'updated_at']) as $industry) {
                $urls[] = [route('site.sector', ['slug' => $industry->slug]), $today, '0.8'];
            }
            foreach (Province::get(['slug']) as $province) {
                $urls[] = [route('site.province', ['slug' => $province->slug]), $today, '0.7'];
            }
            foreach (Tender::whereIn('status', [Tender::PUBLISHED, Tender::AWARDED])->orderByDesc('id')->limit(20000)->get(['slug', 'updated_at']) as $tender) {
                $urls[] = [route('site.tenders.show', ['slug' => $tender->slug]), $tender->updated_at?->toDateString() ?? $today, '0.6'];
            }
            if (Platform::bool('products_enabled')) {
                $urls[] = [route('site.products'), $today, '0.8'];
                foreach (Product::live()->orderByDesc('id')->limit(20000)->get(['slug', 'updated_at']) as $product) {
                    $urls[] = [route('site.products.show', ['slug' => $product->slug]), $product->updated_at?->toDateString() ?? $today, '0.6'];
                }
            }
            foreach (Company::listed()->orderByDesc('id')->limit(20000)->get(['slug', 'updated_at']) as $company) {
                $urls[] = [route('site.companies.show', ['slug' => $company->slug]), $company->updated_at?->toDateString() ?? $today, '0.5'];
            }
        } catch (Throwable) {
            // before the database is set up the fixed pages are still listed
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($urls as [$loc, $changed, $priority]) {
            $xml .= '  <url><loc>'.e($loc).'</loc><lastmod>'.$changed.'</lastmod><priority>'.$priority.'</priority></url>'."\n";
        }

        return response($xml.'</urlset>'."\n", 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /** For search engines and AI crawlers: the website is open, the accounts are not for them. */
    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            'Allow: /',
            'Disallow: /admin',
            'Disallow: /dashboard',
            'Disallow: /my/',
            'Disallow: /company',
            'Disallow: /alerts',
            'Disallow: /verification',
            'Disallow: /subscription',
            'Disallow: /team',
            'Disallow: /profile',
            'Disallow: /notifications',
            'Disallow: /login',
            'Disallow: /forgot-password',
            'Disallow: /reset-password',
            '',
            'Sitemap: '.url('/sitemap.xml'),
        ];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /** For AI assistants (the llms.txt convention): what Tenders.af is, in plain Markdown, with the facts they are asked about. */
    public function llms(): Response
    {
        $name = config('app.name');
        $facts = $this->facts();
        $text = "# $name\n\n> ".config('site.summary')."\n\n";
        $text .= '- Website: '.url('/')."\n- Made by: Fida Technologies, Kabul (https://fida.af)\n- Country covered: Afghanistan, all 34 provinces\n- Language: English\n";
        $text .= "- Company registration: free\n- Price of paid plans: ".$facts['price']."\n- Payment: ".$facts['payments']."\n- Refunds: payments are not refunded\n\n";

        $text .= "## What it does\n\n";
        foreach (config('site.features') as [, $title, $body]) {
            $text .= "- **$title**: $body\n";
        }
        $text .= "\n## How it is used\n\n";
        foreach (config('site.steps') as $i => [$title, $body]) {
            $text .= ($i + 1).". **$title.** $body\n";
        }
        $text .= "\n## Tenders by sector\n\n";
        foreach ($this->sectorList() as $sector) {
            $text .= '- ['.$sector->name.' tenders]('.route('site.sector', ['slug' => $sector->slug]).")\n";
        }
        $text .= "\n## Questions and answers\n\n";
        foreach ($this->faqs() as [$question, $answer]) {
            $text .= "### $question\n\n$answer\n\n";
        }
        $text .= "## Pages\n\n";
        foreach (self::PAGES as [$path, $title]) {
            $text .= "- [$title](".url($path).")\n";
        }

        return response($text, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    private function page(string $view, array $meta): View
    {
        return view($view, $meta + [
            'plans' => $this->plans(),
            'accounts' => $this->accounts(),
            'faqs' => $this->faqs(),
            'facts' => $this->facts(),
            'signup' => Platform::bool('registration'),
            'crumbs' => [],
            'noindex' => false,
        ]);
    }

    /** The sectors in use with the number of open tenders in each. */
    private function sectorList(): Collection
    {
        try {
            return Industry::where('is_active', true)->withCount(['tenders as open_count' => fn (Builder $q) => $q->open()])->orderBy('sort')->orderBy('name')->get();
        } catch (Throwable) {
            return collect();
        }
    }

    /** Open tenders in each province: [province id => count]. */
    private function provinceCounts(): array
    {
        try {
            return Tender::open()->whereNotNull('province_id')->selectRaw('province_id, count(*) as n')->groupBy('province_id')->pluck('n', 'province_id')->map(fn ($n) => (int) $n)->all();
        } catch (Throwable) {
            return [];
        }
    }

    /** The shapes of the 34 provinces with their open tenders, for the map. */
    private function map(array $counts): array
    {
        try {
            $ids = Province::pluck('id', 'slug')->all();
        } catch (Throwable) {
            $ids = [];
        }
        $most = max(1, $counts ? max($counts) : 1);

        return array_map(function (array $shape) use ($ids, $counts, $most) {
            $count = $counts[$ids[$shape['slug']] ?? 0] ?? 0;
            // three steps of green: none or few, some, many (compared with the busiest province)
            $shape['count'] = $count;
            $shape['shade'] = $count === 0 ? 0.10 : ($count / $most > 0.5 ? 0.62 : ($count / $most > 0.15 ? 0.36 : 0.20));

            return $shape;
        }, require resource_path('data/provinces.php'));
    }

    private function plans(): Collection
    {
        try {
            return Plan::where('is_active', true)->orderBy('sort')->orderBy('price')->orderBy('id')->get();
        } catch (Throwable) {
            return collect(); // the site still opens before the database is set up
        }
    }

    private function accounts(): Collection
    {
        try {
            return PlatformAccount::where('is_active', true)->orderBy('name')->get(['name', 'type', 'currency']);
        } catch (Throwable) {
            return collect();
        }
    }

    /** Prices and ways to pay as sentences, from the live settings. */
    private function facts(): array
    {
        $paid = $this->plans()->filter(fn (Plan $plan) => ! $plan->isFree() && $plan->interval === 'monthly');
        // "Basic is 800 AFN or 12 USD a month"
        $priced = $paid->groupBy('name')->map(fn (Collection $same, string $name) => $name.' is '.$same->sortBy('currency')->map(fn (Plan $plan) => $plan->priceText())->join(' or ').' a month')->values();
        $kinds = $this->accounts()->pluck('type')->unique()->map(fn (string $type) => ['bank' => 'bank transfer', 'cash' => 'cash at our office', 'mobile' => 'HesabPay', 'online' => 'online payment', 'crypto' => 'USDT'][$type] ?? null)->filter()->values();

        return [
            'price' => 'Registering a company is free. '.($priced->isEmpty() ? 'The paid plans are shown on the pricing page.' : $priced->join(', ', ' and ').'. Afghan companies pay in AFN; NGOs and international clients pay in USD. A year costs about 20% less than twelve months.'),
            'payments' => $kinds->isEmpty() ? 'The ways to pay are shown on your Subscription page.' : 'You can pay by '.$kinds->join(', ', ' or ').'.',
        ];
    }

    /** The questions with their placeholders filled in. */
    private function faqs(): array
    {
        $facts = $this->facts();

        return array_map(fn (array $faq) => [$faq[0], strtr($faq[1], ['{price}' => $facts['price'], '{payments}' => $facts['payments']])], config('site.faqs'));
    }
}
