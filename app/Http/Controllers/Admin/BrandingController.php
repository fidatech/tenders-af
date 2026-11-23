<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\UserError;
use App\Http\Controllers\Controller;
use App\Models\AdminLog;
use App\Support\Branding;
use App\Support\Platform;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Admin > Branding & Logo: the name, the tagline and the pictures of the platform. Super administrators only. */
class BrandingController extends Controller
{
    public function edit(): View
    {
        return view('admin.branding', [
            'values' => [
                'brand_name' => Platform::get('brand_name'),
                'brand_tagline' => Platform::get('brand_tagline'),
                'brand_logo_alt' => Platform::get('brand_logo_alt'),
                'brand_logo_height' => Branding::height(),
                'brand_logo_height_mobile' => Branding::height('mobile'),
                'brand_display' => Branding::display(),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $image = ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'mimetypes:image/png,image/jpeg,image/webp', 'max:'.Branding::MAX_KB];
        $rules = [
            'brand_name' => ['nullable', 'string', 'max:60'],
            'brand_tagline' => ['nullable', 'string', 'max:160'],
            'brand_logo_alt' => ['nullable', 'string', 'max:120'],
            'brand_logo_height' => ['required', 'integer', 'between:16,120'],
            'brand_logo_height_mobile' => ['required', 'integer', 'between:16,120'],
            'brand_display' => ['required', Rule::in(['logo', 'logo_name'])],
        ];
        foreach (array_keys(Branding::ASSETS) as $asset) {
            $rules['file_'.$asset] = $image;
        }
        $data = $request->validate($rules, [
            'file_*.mimes' => __('Upload a PNG, JPG or WebP picture. SVG files are not accepted because they can carry scripts.'),
            'file_*.mimetypes' => __('Upload a PNG, JPG or WebP picture. SVG files are not accepted because they can carry scripts.'),
            'file_*.max' => __('The picture may be at most :kb KB.', ['kb' => Branding::MAX_KB]),
        ]);

        Platform::set([
            'brand_name' => trim((string) ($data['brand_name'] ?? '')),
            'brand_tagline' => trim((string) ($data['brand_tagline'] ?? '')),
            'brand_logo_alt' => trim((string) ($data['brand_logo_alt'] ?? '')),
            'brand_logo_height' => (string) $data['brand_logo_height'],
            'brand_logo_height_mobile' => (string) $data['brand_logo_height_mobile'],
            'brand_display' => $data['brand_display'],
        ]);

        $uploaded = [];
        foreach (array_keys(Branding::ASSETS) as $asset) {
            if ($file = $request->file('file_'.$asset)) {
                try {
                    Branding::store($asset, $file);
                } catch (UserError $e) {
                    throw ValidationException::withMessages(['file_'.$asset => $e->getMessage()]);
                }
                $uploaded[] = Branding::ASSETS[$asset][0];
            }
        }

        AdminLog::record('changed the branding', $uploaded ? 'Uploaded: '.implode(', ', $uploaded) : null);

        return back()->with('success', __('Branding saved.'));
    }

    public function destroy(string $asset): RedirectResponse
    {
        abort_unless(array_key_exists($asset, Branding::ASSETS), 404);
        Branding::remove($asset);
        AdminLog::record('removed a brand picture', Branding::ASSETS[$asset][0]);

        return back()->with('success', __(':asset removed.', ['asset' => __(Branding::ASSETS[$asset][0])]));
    }
}
