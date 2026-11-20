<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Company;
use App\Models\Industry;
use App\Models\Province;
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
        ], ['website.regex' => __('Write only the address of the website, like yourcompany.af.')]);
        $data['website'] = $data['website'] ? 'https://'.$data['website'] : null;

        $industries = array_map('intval', $data['industries'] ?? []);
        // a category only counts under a sector that is ticked
        $categories = Category::whereIn('id', $data['categories'] ?? [])->whereIn('industry_id', $industries ?: [0])->pluck('id')->all();
        unset($data['industries'], $data['categories'], $data['logo']);

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
