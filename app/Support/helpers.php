<?php

use App\Models\Company;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

if (! function_exists('asset_v')) {
    /**
     * URL of a file in public/ with its modification time appended, so browsers can
     * cache it for a year and still pick up a new version the moment it changes.
     */
    function asset_v(string $path): string
    {
        static $urls = [];

        return $urls[$path] ??= asset($path).'?v='.(is_file(public_path($path)) ? filemtime(public_path($path)) : '0');
    }
}

if (! function_exists('text_dir')) {
    /** Text direction of the current language. */
    function text_dir(): string
    {
        return in_array(app()->getLocale(), ['fa', 'ps', 'ar'], true) ? 'rtl' : 'ltr';
    }
}

if (! function_exists('platform')) {
    /** A setting of the platform (see App\Support\Platform): the saved value, or the one from config. */
    function platform(string $key, mixed $default = null): mixed
    {
        return \App\Support\Platform::get($key, $default);
    }
}

if (! function_exists('company')) {
    /** The business of the signed-in user, or null (admin panel, console, guest). */
    function company(): ?Company
    {
        return auth('web')->user()?->company;
    }
}

if (! function_exists('company_id')) {
    function company_id(): ?int
    {
        return auth('web')->user()?->company_id;
    }
}

if (! function_exists('own')) {
    /** Validation rule: the chosen record must belong to the business of the signed-in user. */
    function own(string $table): Exists
    {
        return Rule::exists($table, 'id')->where('company_id', company_id());
    }
}

if (! function_exists('today_iso')) {
    /** Today in the time zone of the business. */
    function today_iso(): string
    {
        return now(company()?->timezone ?: config('app.timezone'))->toDateString();
    }
}

if (! function_exists('fmt_date')) {
    /** A stored date as people read it: 14 Nov 2026. */
    function fmt_date(mixed $date): string
    {
        if (! $date) {
            return '';
        }

        return ($date instanceof DateTimeInterface ? $date : new DateTimeImmutable(substr((string) $date, 0, 10)))->format('j M Y');
    }
}

if (! function_exists('input_date')) {
    /** A stored date as the value of a date box. */
    function input_date(mixed $date): string
    {
        if (! $date) {
            return '';
        }

        return $date instanceof DateTimeInterface ? $date->format('Y-m-d') : substr((string) $date, 0, 10);
    }
}

if (! function_exists('fmt_datetime')) {
    /** A deadline as people read it, in Kabul time: 21 Nov 2026, 16:00. */
    function fmt_datetime(mixed $date): string
    {
        if (! $date) {
            return '';
        }
        $date = $date instanceof DateTimeInterface ? \Illuminate\Support\Carbon::instance($date) : \Illuminate\Support\Carbon::parse((string) $date);

        return $date->format('j M Y, H:i');
    }
}

if (! function_exists('money')) {
    /** 1234.5 becomes "1,234.50"; with a currency code "1,234.50 USD". */
    function money(mixed $amount, ?string $currency = null): string
    {
        return number_format((float) $amount, 2).($currency ? ' '.$currency : '');
    }
}

if (! function_exists('sums')) {
    /** Totals by currency as one line: "1,200.00 USD · 45,000.00 AFN". An empty list gives "0". */
    function sums(iterable $totals): string
    {
        $parts = [];
        foreach ($totals as $currency => $amount) {
            if ((float) $amount != 0.0) {
                $parts[] = money($amount, (string) $currency);
            }
        }

        return $parts ? implode(' · ', $parts) : '0';
    }
}

if (! function_exists('quick_add')) {
    /**
     * Attributes that let a pick list add a new record in a pop-up without leaving the page:
     *   <select name="client_id" id="client" {{ quick_add(__('New client'), 'clients.create') }}>
     * The script puts a "+ New" link in the label of the list (the label must point at its id).
     */
    function quick_add(string $title, string $route, array $params = []): \Illuminate\Support\HtmlString
    {
        return new \Illuminate\Support\HtmlString('data-quick-add="'.e(route($route, $params)).'" data-quick-title="'.e($title).'"');
    }
}

if (! function_exists('per_page')) {
    function per_page(int $usual = 25): int
    {
        return $usual;
    }
}

if (! function_exists('crud_routes')) {
    /** The six routes of a CrudController screen: list, add, save, edit, update, delete. */
    function crud_routes(string $uri, string $controller, string $name): void
    {
        Route::get($uri, [$controller, 'index'])->name($name.'.index');
        Route::get($uri.'/create', [$controller, 'create'])->name($name.'.create');
        Route::post($uri, [$controller, 'store'])->name($name.'.store');
        Route::get($uri.'/{id}/edit', [$controller, 'edit'])->whereNumber('id')->name($name.'.edit');
        Route::put($uri.'/{id}', [$controller, 'update'])->whereNumber('id')->name($name.'.update');
        Route::delete($uri.'/{id}', [$controller, 'destroy'])->whereNumber('id')->name($name.'.destroy');
    }
}
