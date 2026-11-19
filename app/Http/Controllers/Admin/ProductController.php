<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\BusinessMail;
use App\Models\AdminLog;
use App\Models\Notice;
use App\Models\Product;
use App\Models\ProductPromotion;
use App\Models\Tender;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/** Products of companies wait here to be approved, and payments for sponsoring wait to be confirmed. */
class ProductController extends Controller
{
    /** Why a product can be sent back. The administrator picks one; the company reads the text. */
    public const REASONS = [
        'pictures' => 'The pictures are not clear, or they do not show the product itself.',
        'incomplete' => 'Important details are missing: please describe the product, the unit and the smallest order.',
        'retail' => 'This page is for wholesale only. Please give a wholesale unit and a smallest order.',
        'not_product' => 'This is not a product. Services and job offers are not listed here.',
        'duplicate' => 'This product is already listed.',
        'not_allowed' => 'This kind of product cannot be advertised here.',
        'contact' => 'Please do not write phone numbers or links in the text. Buyers contact you through the buttons.',
    ];

    public function index(Request $request): View
    {
        $status = isset(Product::STATUSES[$request->query('status')]) ? $request->query('status') : null;
        $term = trim((string) $request->query('q', ''));

        return view('admin.products.index', [
            'status' => $status,
            'term' => $term,
            'rows' => Product::query()->when($status, fn (Builder $q) => $q->where('status', $status))
                ->when($term !== '', fn (Builder $q) => $q->where(fn (Builder $inner) => $inner->where('name', 'like', "%$term%")->orWhereHas('company', fn (Builder $c) => $c->where('name', 'like', "%$term%"))))
                ->with('company', 'industry', 'images')->orderByRaw("case when status = 'pending' then 0 else 1 end")->orderByDesc('id')->paginate(25)->withQueryString(),
            'counts' => Product::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')->all(),
            'payments' => ProductPromotion::where('status', ProductPromotion::PENDING)->with('product', 'company', 'account')->orderBy('id')->get(),
            'sponsored' => Product::sponsored()->count(),
        ]);
    }

    public function show(int $id): View
    {
        return view('admin.products.show', [
            'product' => Product::with('company.province', 'industry', 'province', 'images', 'promotions.account')->findOrFail($id),
            'reasons' => self::REASONS,
        ]);
    }

    public function approve(int $id): RedirectResponse
    {
        $product = Product::where('status', Product::PENDING)->findOrFail($id);
        $product->update(['status' => Product::PUBLISHED, 'reject_reason' => null, 'published_at' => $product->published_at ?? now()]);
        AdminLog::record('approved product', $product->name, $product->company?->name);
        $this->tell($product, 'Your product is published', ['Buyers can see it on the products page now.']);

        return redirect()->route('admin.products.index', ['status' => Product::PENDING])->with('success', __('The product is published.'));
    }

    public function reject(Request $request, int $id): RedirectResponse
    {
        $product = Product::where('status', Product::PENDING)->findOrFail($id);
        $data = $request->validate(['reason' => ['required', Rule::in(array_keys(self::REASONS))]]);
        $text = self::REASONS[$data['reason']];
        $product->update(['status' => Product::REJECTED, 'reject_reason' => $text]);
        AdminLog::record('sent product back', $product->name, $text);
        $this->tell($product, 'Your product was not published', [$text, 'You can correct it and send it again from My products.']);

        return redirect()->route('admin.products.index', ['status' => Product::PENDING])->with('success', __('The company is told why.'));
    }

    /** Take a published product off the site, or put it back. */
    public function hide(int $id): RedirectResponse
    {
        $product = Product::whereIn('status', [Product::PUBLISHED, Product::HIDDEN])->findOrFail($id);
        $product->update(['status' => $product->status === Product::HIDDEN ? Product::PUBLISHED : Product::HIDDEN]);
        AdminLog::record($product->status === Product::HIDDEN ? 'hid product' : 'showed product again', $product->name);

        return back()->with('success', $product->status === Product::HIDDEN ? __('The product is off the site.') : __('The product is on the site again.'));
    }

    /** The payment arrived: the product is sponsored from today, or for longer if it already is. */
    public function approvePayment(int $id): RedirectResponse
    {
        $payment = ProductPromotion::where('status', ProductPromotion::PENDING)->with('product')->findOrFail($id);
        $product = $payment->product;
        $today = Carbon::parse(Tender::now()->toDateString());
        $from = $product->isSponsored() ? $product->sponsored_until->copy()->addDay() : $today;
        $until = $from->copy()->addDays($payment->days - 1);

        $payment->update(['status' => ProductPromotion::APPROVED, 'starts_on' => $from->toDateString(), 'ends_on' => $until->toDateString(), 'admin_id' => auth('admin')->id()]);
        $product->update(['sponsored_until' => $until->toDateString()]);
        AdminLog::record('confirmed sponsoring', $product->name, $payment->amount.' '.$payment->currency.' until '.$until->toDateString());
        $this->tell($product, 'Your product is sponsored', ['It is shown first, with a "Sponsored" label, until '.fmt_date($until).'.']);

        return back()->with('success', __('The product is sponsored until :date.', ['date' => fmt_date($until)]));
    }

    public function rejectPayment(Request $request, int $id): RedirectResponse
    {
        $payment = ProductPromotion::where('status', ProductPromotion::PENDING)->with('product')->findOrFail($id);
        $data = $request->validate(['note' => ['required', 'string', 'max:255']]);
        $payment->update(['status' => ProductPromotion::REJECTED, 'note' => $data['note'], 'admin_id' => auth('admin')->id()]);
        AdminLog::record('refused sponsoring payment', $payment->product->name, $data['note']);
        $this->tell($payment->product, 'Your payment for sponsoring was not confirmed', [$data['note']]);

        return back()->with('success', __('The company is told.'));
    }

    public function receipt(int $id): StreamedResponse
    {
        $payment = ProductPromotion::findOrFail($id);
        abort_unless($payment->receipt_path && Storage::disk('local')->exists($payment->receipt_path), 404);

        return Storage::disk('local')->response($payment->receipt_path);
    }

    public function destroy(int $id): RedirectResponse
    {
        $product = Product::with('images')->findOrFail($id);
        foreach ($product->images as $image) {
            Storage::disk('local')->delete($image->path);
        }
        if ($product->video_path) {
            Storage::disk('local')->delete($product->video_path);
        }
        AdminLog::record('deleted product', $product->name, $product->company?->name);
        $product->delete();

        return redirect()->route('admin.products.index')->with('success', __('The product is deleted.'));
    }

    /** A notification under the bell of every user of the company and an email to its owner. */
    private function tell(Product $product, string $subject, array $lines): void
    {
        $company = $product->company;
        if (! $company) {
            return;
        }
        $url = route('products.edit', ['id' => $product->id]);
        foreach (User::where('company_id', $company->id)->where('is_active', true)->get() as $user) {
            Notice::create(['company_id' => $company->id, 'user_id' => $user->id, 'title' => $subject, 'body' => $product->name, 'url' => $url]);
        }
        if ($to = $company->owner()?->email) {
            try {
                Mail::to($to)->send(new BusinessMail($company, $subject, array_merge([$product->name], $lines), 'Open the product', $url));
            } catch (Throwable $e) {
                report($e);
            }
        }
    }
}
