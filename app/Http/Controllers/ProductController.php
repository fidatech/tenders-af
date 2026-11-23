<?php

namespace App\Http\Controllers;

use App\Exceptions\UserError;
use App\Models\AdminNotice;
use App\Models\Company;
use App\Models\Industry;
use App\Models\PlatformAccount;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductPromotion;
use App\Models\Province;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The products a company offers for wholesale. A listing is free up to a number set by the
 * administrators and is checked before it is shown. Sponsoring a product (first place and a
 * "Sponsored" label for a week or a month) is paid the same way as a subscription: the company
 * pays, uploads the proof, and an administrator confirms.
 */
class ProductController extends Controller
{
    public function index(): View
    {
        $company = $this->company();

        return view('products.index', [
            'company' => $company,
            'limit' => (int) platform('products_free_limit'),
            'rows' => Product::where('company_id', $company->id)->with('images', 'industry')
                ->withCount(['promotions as waiting' => fn ($q) => $q->where('status', ProductPromotion::PENDING)])
                ->orderByDesc('id')->paginate(per_page(20)),
            'canSponsor' => ProductPromotion::prices($company->currency) !== [],
        ]);
    }

    public function create(): View
    {
        $company = $this->company();
        $this->roomFor($company);

        return view('products.form', $this->lists() + ['product' => null, 'company' => $company]);
    }

    public function store(Request $request): RedirectResponse
    {
        $company = $this->company();
        $this->roomFor($company);
        $data = $this->validated($request, null);
        $send = $request->input('action') === 'send';

        $product = Product::create($data + [
            'company_id' => $company->id,
            'slug' => Product::freeSlug($data['name']),
            'status' => $send ? Product::PENDING : Product::DRAFT,
            'submitted_at' => $send ? now() : null,
        ]);
        $this->media($product, $request);
        if ($send && $product->images()->doesntExist()) {
            $product->update(['status' => Product::DRAFT, 'submitted_at' => null]);

            return redirect()->route('products.edit', ['id' => $product->id])->with('danger', __('Add at least one picture before you send the product.'));
        }

        if ($send) {
            AdminNotice::toAll('product', 'Product to approve: '.$product->name, $company->name, route('admin.products.show', ['id' => $product->id]));
        }

        return redirect()->route('products.edit', ['id' => $product->id])->with('success', $send
            ? __('Your product is sent. We check it and publish it, usually within one working day.')
            : __('Your product is saved as a draft.'));
    }

    public function edit(int $id): View
    {
        return view('products.form', $this->lists() + ['product' => $this->mine($id, ['images']), 'company' => company()]);
    }

    /** A change to a product that is already shown is checked again before visitors see it. */
    public function update(Request $request, int $id): RedirectResponse
    {
        $product = $this->mine($id, ['images']);
        if ($product->status === Product::HIDDEN) {
            throw new UserError(__('This product was taken off the site. Please contact support.'));
        }
        $data = $this->validated($request, $product);
        $send = $request->input('action') === 'send' || $product->status === Product::PUBLISHED || $product->status === Product::PENDING;

        if ($request->boolean('remove_video') && $product->video_path) {
            Storage::disk('local')->delete($product->video_path);
            $data['video_path'] = null;
        }
        $product->update($data);
        $this->media($product, $request);

        if ($send && $product->images()->doesntExist()) {
            $product->update(['status' => Product::DRAFT]);
            throw new UserError(__('Add at least one picture before you send the product.'));
        }
        if ($send) {
            $product->update(['status' => Product::PENDING, 'submitted_at' => now(), 'reject_reason' => null]);
            AdminNotice::toAll('product', 'Product to approve: '.$product->name, company()->name, route('admin.products.show', ['id' => $product->id]));
        }

        return back()->with('success', $send ? __('Saved. We check the product before it is shown.') : __('The product is saved.'));
    }

    public function destroy(int $id): RedirectResponse
    {
        $product = $this->mine($id, ['images']);
        if ($product->promotions()->where('status', ProductPromotion::PENDING)->exists()) {
            throw new UserError(__('A payment for this product is being checked. Please wait for it.'));
        }
        foreach ($product->images as $image) {
            Storage::disk('local')->delete($image->path);
        }
        if ($product->video_path) {
            Storage::disk('local')->delete($product->video_path);
        }
        $product->delete();

        return redirect()->route('products.index')->with('success', __('The product is deleted.'));
    }

    public function destroyImage(int $id, int $image): RedirectResponse
    {
        $product = $this->mine($id);
        $file = ProductImage::where('product_id', $product->id)->findOrFail($image);
        if ($product->status !== Product::DRAFT && $product->images()->count() <= 1) {
            throw new UserError(__('A product needs at least one picture. Add another one first.'));
        }
        Storage::disk('local')->delete($file->path);
        $file->delete();

        return back()->with('success', __('The picture is removed.'));
    }

    /** Choose a week or a month, see where to pay, and upload the proof. */
    public function sponsor(int $id): View
    {
        $product = $this->mine($id, ['images']);
        $company = company();

        return view('products.sponsor', [
            'product' => $product,
            'company' => $company,
            'prices' => ProductPromotion::prices($company->currency),
            'accounts' => PlatformAccount::where('is_active', true)->where('currency', $company->currency)->orderBy('name')->get(),
            'waiting' => $product->promotions()->where('status', ProductPromotion::PENDING)->latest('id')->first(),
            'history' => $product->promotions()->where('status', '!=', ProductPromotion::PENDING)->latest('id')->limit(10)->get(),
        ]);
    }

    public function storeSponsor(Request $request, int $id): RedirectResponse
    {
        $product = $this->mine($id);
        $company = company();
        $prices = ProductPromotion::prices($company->currency);

        if ($product->status !== Product::PUBLISHED) {
            throw new UserError(__('Only a published product can be sponsored.'));
        }
        if ($product->promotions()->where('status', ProductPromotion::PENDING)->exists()) {
            throw new UserError(__('Your payment for this product is already waiting to be confirmed.'));
        }
        $data = $request->validate([
            'period' => ['required', Rule::in(array_keys($prices))],
            'platform_account_id' => ['nullable', Rule::exists('platform_accounts', 'id')->where('is_active', true)],
            'reference' => ['nullable', 'string', 'max:255'],
            'receipt' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ], ['receipt.required' => __('Please add the screenshot of your payment.'), 'period.required' => __('Choose a week or a month first.')]);

        ProductPromotion::create([
            'product_id' => $product->id,
            'company_id' => $company->id,
            'platform_account_id' => $data['platform_account_id'] ?? null,
            'period' => $data['period'],
            'days' => ProductPromotion::PERIODS[$data['period']][1],
            'amount' => $prices[$data['period']],
            'currency' => $company->currency,
            'reference' => $data['reference'] ?? null,
            'receipt_path' => $request->file('receipt')->store('receipts/'.$company->id, 'local'),
        ]);

        AdminNotice::toAll('payment', $company->name.' paid to sponsor a product', $product->name.' · '.number_format($prices[$data['period']]).' '.$company->currency, route('admin.products.index'));

        return redirect()->route('products.index')->with('success', __('Thank you. We check your payment and then your product is sponsored.'));
    }

    /** The module is off until an administrator switches it on. */
    private function company(): Company
    {
        abort_unless(module_on('products'), 404);

        return company();
    }

    /** A company lists a limited number of products for free. */
    private function roomFor(Company $company): void
    {
        $limit = (int) platform('products_free_limit');
        if ($limit > 0 && Product::where('company_id', $company->id)->count() >= $limit) {
            throw new UserError(__('You can list :count products. Delete one to add another.', ['count' => $limit]));
        }
    }

    private function mine(int $id, array $with = []): Product
    {
        return Product::where('company_id', $this->company()->id)->with($with)->findOrFail($id);
    }

    private function lists(): array
    {
        return ['industries' => Industry::options(), 'provinces' => Province::options()];
    }

    private function validated(Request $request, ?Product $product): array
    {
        $room = Product::MAX_IMAGES - ($product ? $product->images->count() : 0);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'industry_id' => ['required', Rule::exists('industries', 'id')],
            'province_id' => ['nullable', Rule::exists('provinces', 'id')],
            'summary' => ['required', 'string', 'max:300'],
            'description' => ['nullable', 'string', 'max:10000'],
            'unit' => ['required', 'string', 'max:50'],
            'min_order' => ['required', 'string', 'max:100'],
            'capacity' => ['nullable', 'string', 'max:150'],
            'price_min' => ['nullable', 'numeric', 'min:0'],
            'price_max' => ['nullable', 'numeric', 'gte:price_min'],
            'price_currency' => ['nullable', 'required_with:price_min', Rule::in(config('tenders.currencies'))],
            'images' => ['nullable', 'array', 'max:'.max(0, $room)],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'video' => ['nullable', 'file', 'mimetypes:video/mp4,video/webm,video/quicktime', 'max:30720'],
        ], [
            'images.max' => __('A product has up to :count pictures.', ['count' => Product::MAX_IMAGES]),
            'video.max' => __('The video may be up to 30 MB: keep it under one minute.'),
            'min_order.required' => __('Say the smallest order you accept. Products here are for wholesale only.'),
        ], ['price_min' => __('price'), 'price_max' => __('highest price'), 'industry_id' => __('sector'), 'province_id' => __('province'), 'min_order' => __('smallest order')]);

        unset($data['images'], $data['video']);
        $data['made_in_afghanistan'] = $request->boolean('made_in_afghanistan');
        if (($data['price_min'] ?? null) === null) {
            $data['price_min'] = $data['price_max'] = $data['price_currency'] = null;
        }
        if ($product && $data['name'] !== $product->name && $product->status === Product::DRAFT) {
            $data['slug'] = Product::freeSlug($data['name'], $product->id);
        }

        return $data;
    }

    private function media(Product $product, Request $request): void
    {
        $sort = (int) $product->images()->max('sort');
        foreach ($request->file('images', []) as $file) {
            ProductImage::create(['product_id' => $product->id, 'path' => $file->store('products/'.$product->id, 'local'), 'sort' => ++$sort]);
        }
        if ($video = $request->file('video')) {
            if ($product->video_path) {
                Storage::disk('local')->delete($product->video_path);
            }
            $product->update(['video_path' => $video->store('products/'.$product->id, 'local')]);
        }
    }
}
