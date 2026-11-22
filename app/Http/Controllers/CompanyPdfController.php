<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CompanyProject;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * The company profile as an A4 document (Company Profile PDF Generator module).
 *
 * The document is print-ready HTML: @page A4, a cover page, page breaks and page numbers, saved as
 * a PDF with the browser's "Save as PDF". Text in Dari or Pashto is laid out right to left with the
 * fonts in public/fonts. A server-side PDF file needs a PDF library (for example mpdf/mpdf); the
 * view is written so it can be handed to one unchanged.
 *
 * Contact details follow the company's sharing choice. The public version never carries restricted
 * details; the "full" version is only for the people of the company itself.
 */
class CompanyPdfController extends Controller
{
    public const VARIANTS = [
        'short' => ['Short profile', 'One page: who you are, what you do and how to reach you.'],
        'standard' => ['Standard profile', 'Adds your services, featured projects and sectors.'],
        'detailed' => ['Detailed profile', 'Every published project and product, with pictures.'],
    ];

    /** The page in the panel: choose a version and open it. */
    public function show(): View
    {
        $company = company();

        return view('pdf.show', [
            'company' => $company,
            'variants' => self::VARIANTS,
            'profile' => $company->completeness(),
        ]);
    }

    /** The company's own document: contacts as the company chooses (public or full). */
    public function print(Request $request): Response
    {
        $company = company();
        $contacts = $request->query('contacts') === 'full' ? 'full' : 'public';

        return $this->render($company, $this->variant($request), $contacts, true);
    }

    /** The document a visitor opens from the company page: never more than the page itself shows. */
    public function public(Request $request, string $slug): Response
    {
        $company = Company::listed()->where('slug', $slug)->firstOrFail();

        return $this->render($company, $this->variant($request), 'visitor', false);
    }

    private function variant(Request $request): string
    {
        return array_key_exists((string) $request->query('variant'), self::VARIANTS) ? $request->query('variant') : 'standard';
    }

    /**
     * @param  string  $contacts  public: the details the company approved for everyone; full: every approved
     *                            detail (the company's own copy); visitor: what this visitor may see
     */
    private function render(Company $company, string $variant, string $contacts, bool $own): Response
    {
        $company->loadMissing('province', 'industries');
        $fields = match ($contacts) {
            'full' => array_filter(array_combine($company->approvedContactFields(), array_map(fn ($f) => $company->{$f}, $company->approvedContactFields()))),
            'public' => $company->contactFor(null)['fields'],
            default => $company->contactFor(auth('web')->user())['fields'],
        };

        $projects = collect();
        if (module_on('portfolios') && $variant !== 'short') {
            $projects = CompanyProject::where('company_id', $company->id)->where('status', CompanyProject::PUBLISHED)->where('review_status', '!=', 'rejected')
                ->with('images', 'industry')->orderByDesc('is_featured')->orderByDesc('completed_on')->orderByDesc('id')
                ->limit($variant === 'standard' ? 4 : 30)->get();
        }
        $products = $variant === 'detailed' && module_on('products')
            ? Product::live()->where('company_id', $company->id)->with('images')->limit(12)->get()
            : collect();

        $html = view('pdf.profile', [
            'company' => $company,
            'variant' => $variant,
            'variantLabel' => self::VARIANTS[$variant][0],
            'fields' => $fields,
            'contactsNote' => $contacts === 'full' ? 'Full profile with every contact detail you approved. For your own use.' : null,
            'services' => module_on('mini_websites') ? $company->serviceList() : [],
            'projects' => $projects,
            'products' => $products,
            'pageUrl' => module_on('directory') && $company->is_listed ? route('site.companies.show', ['slug' => $company->slug]) : null,
            'own' => $own,
        ])->render();

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'X-Robots-Tag' => 'noindex, nofollow',
            // what is shown depends on who asks (a subscriber may see more): never kept by shared caches
            'Cache-Control' => $own ? 'private, no-store' : 'private, max-age=300',
        ]);
    }
}
