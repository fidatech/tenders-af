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
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'url', 'max:255'],
            'country' => ['nullable', 'string', 'max:100'],
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
        ]);

        $industries = array_map('intval', $data['industries'] ?? []);
        // a category only counts under a sector that is ticked
        $categories = Category::whereIn('id', $data['categories'] ?? [])->whereIn('industry_id', $industries ?: [0])->pluck('id')->all();
        unset($data['industries'], $data['categories'], $data['logo']);

        $data['is_listed'] = $request->boolean('is_listed');
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
