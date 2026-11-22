<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Company;
use App\Models\Industry;
use App\Models\Province;
use App\Support\Modules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** The public profile of the company: who it is, where it works and what it supplies. Only the owner opens this screen. */
class SettingsController extends Controller
{
    public function edit(): View
    {
        $company = company();

        return view('settings.edit', [
            'company' => $company,
            'provinces' => Province::options(),
            'industries' => Industry::where('is_active', true)->with(['categories' => fn ($q) => $q->where('is_active', true)->orderBy('name')])->orderBy('sort')->orderBy('name')->get(),
            'mineIndustries' => $company->industries()->pluck('industries.id')->all(),
            'mineCategories' => $company->categories()->pluck('categories.id')->all(),
            'profile' => $company->completeness(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $company = company();
        // "https://www.x.af/" or "x.af": only the address is kept, https:// is put back when it is saved
        $request->merge(['website' => Company::websiteHost($request->input('website'))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'string', 'max:200', 'regex:'.Company::DOMAIN],
            'country' => ['nullable', 'string', Rule::in(array_values(config('countries')))],
            'province_id' => ['nullable', 'exists:provinces,id'],
            'address' => ['nullable', 'string', 'max:255'],
            'about' => ['nullable', 'string', 'max:3000'],
            'license_no' => ['nullable', 'string', 'max:100'],
            'tin' => ['nullable', 'string', 'max:100'],
            'founded_year' => ['nullable', 'integer', 'between:1900,'.date('Y')],
            'size' => ['nullable', Rule::in(array_keys(Company::SIZES))],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'industries' => ['nullable', 'array'],
            'industries.*' => ['integer', Rule::exists('industries', 'id')->where('is_active', true)],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['integer', Rule::exists('categories', 'id')->where('is_active', true)],
            'email' => ['nullable', 'email', 'max:255'],
            'business_types' => ['nullable', 'array'],
            'business_types.*' => [Rule::in(array_keys(Company::BUSINESS_TYPES))],
            'contact_visibility' => ['nullable', Rule::in(array_keys(Company::CONTACT_VISIBILITY))],
            'contact_fields' => ['nullable', 'array'],
            'contact_fields.*' => [Rule::in(array_keys(Company::CONTACT_FIELDS))],
            // the richer public page (Company Mini-Websites module)
            'tagline' => ['nullable', 'string', 'max:160'],
            'services' => ['nullable', 'array', 'max:20'],
            'services.*.name' => ['nullable', 'string', 'max:100'],
            'services.*.text' => ['nullable', 'string', 'max:300'],
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096', 'dimensions:min_width=600,max_width=6000,max_height=6000'],
            'remove_cover' => ['nullable', 'boolean'],
            'page_template' => ['nullable', Rule::in(array_keys(Company::TEMPLATES))],
            'page_accent' => ['nullable', Rule::in(array_keys(Company::ACCENTS))],
        ], ['website.regex' => __('Write only the address of the website, like yourcompany.af.')]);
        $data['website'] = $data['website'] ? 'https://'.$data['website'] : null;

        $industries = array_map('intval', $data['industries'] ?? []);
        // a category only counts under a sector that is ticked
        $categories = Category::whereIn('id', $data['categories'] ?? [])->whereIn('industry_id', $industries ?: [0])->pluck('id')->all();
        unset($data['industries'], $data['categories'], $data['logo'], $data['cover'], $data['remove_cover']);

        // the ticks are only read when the form had them (a hidden field says so): an older form keeps what was saved
        if ($request->has('business_types_shown')) {
            $data['business_types'] = array_values(array_unique($data['business_types'] ?? []));
        } else {
            unset($data['business_types']);
        }
        if ($request->has('contact_shown')) {
            $data['contact_visibility'] = $data['contact_visibility'] ?? 'public';
            // the details the company approved for its page; nothing ticked means nothing is shown
            $data['contact_fields'] = array_values(array_unique($data['contact_fields'] ?? []));
        } else {
            unset($data['contact_visibility'], $data['contact_fields']);
        }

        if (Modules::enabled('mini_websites') && $request->has('page_shown')) {
            $data['services'] = collect($data['services'] ?? [])
                ->map(fn ($row) => ['name' => trim((string) ($row['name'] ?? '')), 'text' => trim((string) ($row['text'] ?? ''))])
                ->filter(fn ($row) => $row['name'] !== '')->values()->all();
            if ($request->boolean('remove_cover') && $company->cover_path) {
                Storage::disk('local')->delete($company->cover_path);
                $data['cover_path'] = null;
            }
            if ($file = $request->file('cover')) {
                if ($company->cover_path) {
                    Storage::disk('local')->delete($company->cover_path);
                }
                $data['cover_path'] = $file->store('covers', 'local');
            }
        } else {
            // the module is off: what was saved before stays as it is
            unset($data['tagline'], $data['services'], $data['page_template'], $data['page_accent']);
        }

        $data['is_listed'] = $request->boolean('is_listed');
        $data['is_buyer'] = $request->boolean('is_buyer');
        // an account always keeps one role: without posting, it looks for tenders
        $data['is_supplier'] = $request->boolean('is_supplier') || ! $data['is_buyer'];
        if ($data['name'] !== $company->name) {
            $data['slug'] = Company::freeSlug($data['name'], $company->id);
        }
        if ($file = $request->file('logo')) {
            if ($company->logo_path) {
                Storage::disk('local')->delete($company->logo_path);
            }
            $data['logo_path'] = $file->store('logos', 'local');
        }

        $company->update($data);
        $company->industries()->sync($industries);
        $company->categories()->sync($categories);

        return back()->with('success', __('Your company profile is saved.'));
    }
}
