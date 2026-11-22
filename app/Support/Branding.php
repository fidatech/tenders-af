<?php

namespace App\Support;

use App\Exceptions\UserError;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The name, tagline and logos of the platform, set under Admin > Branding & Logo.
 *
 * Nothing is hardcoded in the templates: they ask this class. While no logo is uploaded the
 * site keeps its text brand ("Tenders.af"), so there is never a broken or empty picture.
 *
 * Every uploaded picture is decoded and saved again with GD. That throws away metadata and
 * anything hidden in the file, and refuses files that are not really pictures. SVG is not
 * accepted: it can carry scripts and cannot be cleaned reliably without a dedicated library.
 */
class Branding
{
    /** asset => [label, help, longest side in pixels it is stored at] */
    public const ASSETS = [
        'logo' => ['Main logo', 'Shown in the website header on light and dark backgrounds unless a dark version is uploaded. A wide PNG or WebP with a transparent background works best.', 1200],
        'logo_dark' => ['Logo for dark backgrounds', 'Optional. Used on the dark header and footer of the website and in the PDF covers.', 1200],
        'logo_mobile' => ['Mobile logo', 'Optional. A shorter version for phones. Without it the main logo is used.', 800],
        'icon' => ['Compact logo or icon', 'Optional. A square mark used in the admin panel, the app icon on phones and next to the name. At least 512 × 512 pixels.', 1024],
        'favicon' => ['Favicon', 'Optional. A square picture shown in browser tabs. Without it the icon, or the built-in mark, is used.', 512],
    ];

    /** The sizes of the app icon for phones (web app manifest). */
    public const APP_ICON_SIZES = [192, 512];

    public const MAX_KB = 2048;

    public const DISK = 'local';

    public const DEFAULT_NAME = 'Tenders.af';

    public static function name(): string
    {
        $name = trim((string) Platform::get('brand_name'));

        return $name !== '' ? $name : (config('app.name') ?: self::DEFAULT_NAME);
    }

    public static function tagline(): string
    {
        $tagline = trim((string) Platform::get('brand_tagline'));

        return $tagline !== '' ? $tagline : (string) config('site.tagline');
    }

    /** The name split for the text brand: "Tenders.af" becomes ["Tenders", ".af"] so ".af" can be coloured. */
    public static function nameParts(): array
    {
        $name = self::name();
        $dot = strrpos($name, '.');
        if ($dot === false || $dot === 0) {
            return [$name, ''];
        }

        return [substr($name, 0, $dot), substr($name, $dot)];
    }

    public static function path(string $asset): ?string
    {
        $path = Platform::get('brand_'.$asset);

        return is_string($path) && $path !== '' && Storage::disk(self::DISK)->exists($path) ? $path : null;
    }

    public static function has(string $asset): bool
    {
        return self::path($asset) !== null;
    }

    /** The address of an uploaded picture, with its version so browsers fetch a new one at once. */
    public static function url(string $asset, bool $absolute = true): ?string
    {
        $path = self::path($asset);
        if (! $path) {
            return null;
        }

        return route('brand.asset', ['asset' => $asset, 'v' => substr(md5($path), 0, 10)], $absolute);
    }

    /**
     * The picture for a place: "light" (a light background), "dark" (a dark background) or "mobile".
     * Falls back from the special versions to the main logo, and to null when there is none.
     */
    public static function logoFor(string $background = 'light'): ?string
    {
        $order = match ($background) {
            'dark' => ['logo_dark', 'logo'],
            'mobile' => ['logo_mobile', 'logo'],
            'mobile-dark' => ['logo_mobile', 'logo_dark', 'logo'],
            default => ['logo'],
        };
        foreach ($order as $asset) {
            if ($url = self::url($asset)) {
                return $url;
            }
        }

        return null;
    }

    public static function hasLogo(): bool
    {
        return self::has('logo') || self::has('logo_dark') || self::has('logo_mobile');
    }

    /** Height of the logo in the header, in CSS pixels. */
    public static function height(string $where = 'desktop'): int
    {
        $value = (int) Platform::get($where === 'mobile' ? 'brand_logo_height_mobile' : 'brand_logo_height');

        return max(16, min(120, $value ?: ($where === 'mobile' ? 30 : 40)));
    }

    /** "logo" (the picture alone) or "logo_name" (the picture with the name beside it). */
    public static function display(): string
    {
        return Platform::get('brand_display') === 'logo_name' ? 'logo_name' : 'logo';
    }

    public static function alt(): string
    {
        $alt = trim((string) Platform::get('brand_logo_alt'));

        return $alt !== '' ? $alt : self::name();
    }

    /** The favicon: the uploaded one, the icon, or the built-in mark. */
    public static function faviconUrl(): string
    {
        return self::url('favicon') ?? self::url('icon') ?? asset_v('img/favicon.svg');
    }

    public static function faviconType(): string
    {
        $url = self::url('favicon') ?? self::url('icon');

        return $url ? 'image/png' : 'image/svg+xml';
    }

    /** The square icon for phones ("Add to Home Screen"), when one was uploaded. */
    public static function appIconUrl(int $size): ?string
    {
        $path = self::path('icon') ?? self::path('favicon');
        if (! $path) {
            return null;
        }
        $variant = self::variantPath($path, $size);

        return Storage::disk(self::DISK)->exists($variant)
            ? route('brand.asset', ['asset' => 'app-icon-'.$size, 'v' => substr(md5($variant), 0, 10)])
            : null;
    }

    /** Where the square copy of the icon in one size is kept. */
    public static function variantPath(string $path, int $size): string
    {
        return preg_replace('/\.[a-z]+$/i', '', $path).'-'.$size.'.png';
    }

    /** The file of a served asset ("logo", "favicon", "app-icon-192" ...), or null. */
    public static function fileFor(string $asset): ?string
    {
        if (preg_match('/^app-icon-(\d+)$/', $asset, $m) && in_array((int) $m[1], self::APP_ICON_SIZES, true)) {
            $source = self::path('icon') ?? self::path('favicon');
            $variant = $source ? self::variantPath($source, (int) $m[1]) : null;

            return $variant && Storage::disk(self::DISK)->exists($variant) ? $variant : null;
        }

        return array_key_exists($asset, self::ASSETS) ? self::path($asset) : null;
    }

    /**
     * Save an uploaded picture for one asset, replacing the old one. Returns the stored path.
     *
     * @throws UserError when the file is not a picture GD can read
     */
    public static function store(string $asset, UploadedFile $file): string
    {
        abort_unless(array_key_exists($asset, self::ASSETS), 404);
        $maxSide = self::ASSETS[$asset][2];

        $image = self::decode($file);
        $image = self::fit($image, $maxSide);
        $square = in_array($asset, ['icon', 'favicon'], true);

        $path = 'branding/'.$asset.'-'.Str::lower(Str::random(12)).'.png';
        Storage::disk(self::DISK)->put($path, self::png($image));

        if ($square) {
            foreach (self::APP_ICON_SIZES as $size) {
                Storage::disk(self::DISK)->put(self::variantPath($path, $size), self::png(self::squared($image, $size)));
            }
        }
        self::remove($asset);
        Platform::set(['brand_'.$asset => $path]);

        return $path;
    }

    /** Take an asset away; the site falls back to the text brand or the next version. */
    public static function remove(string $asset): void
    {
        $old = Platform::get('brand_'.$asset);
        if (is_string($old) && $old !== '') {
            $disk = Storage::disk(self::DISK);
            $disk->delete($old);
            foreach (self::APP_ICON_SIZES as $size) {
                $disk->delete(self::variantPath($old, $size));
            }
        }
        Platform::set(['brand_'.$asset => '']);
    }

    /** @return \GdImage */
    private static function decode(UploadedFile $file)
    {
        $bytes = @file_get_contents($file->getRealPath());
        $info = $bytes !== false ? @getimagesizefromstring($bytes) : false;
        if (! $info || ! in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_WEBP], true)) {
            throw new UserError(__('The file is not a PNG, JPG or WebP picture.'));
        }
        if ($info[0] > 6000 || $info[1] > 6000 || $info[0] < 16 || $info[1] < 16) {
            throw new UserError(__('The picture must be between 16 and 6000 pixels on each side.'));
        }
        $image = @imagecreatefromstring($bytes);
        if (! $image) {
            throw new UserError(__('The picture could not be read. Save it again as PNG and upload it once more.'));
        }
        imagealphablending($image, false);
        imagesavealpha($image, true);

        return $image;
    }

    /** Shrink (never enlarge) so the longest side is at most $max pixels, keeping the proportions. */
    private static function fit($image, int $max)
    {
        $w = imagesx($image);
        $h = imagesy($image);
        if (max($w, $h) <= $max) {
            return $image;
        }
        $scale = $max / max($w, $h);
        $resized = imagescale($image, max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale)), IMG_BICUBIC);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);

        return $resized;
    }

    /** The picture centred on a transparent square of $size pixels. */
    private static function squared($image, int $size)
    {
        $w = imagesx($image);
        $h = imagesy($image);
        $scale = $size / max($w, $h);
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));
        $canvas = imagecreatetruecolor($size, $size);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        imagecopyresampled($canvas, $image, (int) (($size - $nw) / 2), (int) (($size - $nh) / 2), 0, 0, $nw, $nh, $w, $h);

        return $canvas;
    }

    private static function png($image): string
    {
        ob_start();
        imagepng($image, null, 7);

        return (string) ob_get_clean();
    }
}
