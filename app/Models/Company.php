<?php

namespace App\Models;

use App\Casts\Day;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A company, NGO or international organisation registered on Tenders.af. Registration is free:
 * it gives a public profile in the directory. A paid plan opens the full tenders and the alerts.
 */
class Company extends Model
{
    public const TRIAL = 'trial';

    public const ACTIVE = 'active';

    public const SUSPENDED = 'suspended';

    /** kind => [label, the currency it pays in] */
    public const KINDS = [
        'company' => ['Afghan company', 'AFN'],
        'government' => ['Government office or ministry', 'AFN'],
        'ngo' => ['NGO', 'USD'],
        'international' => ['International company or organisation', 'USD'],
    ];

    public const SIZES = ['1-10' => '1 to 10 people', '11-50' => '11 to 50 people', '51-200' => '51 to 200 people', '200+' => 'More than 200 people'];

    public const VERIFY = ['none' => 'Not verified', 'pending' => 'Waiting for review', 'verified' => 'Verified', 'rejected' => 'Not accepted'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'alert_types' => 'array',
            'alert_email' => 'boolean',
            'alert_whatsapp' => 'boolean',
            'is_listed' => 'boolean',
            'is_supplier' => 'boolean',
            'is_buyer' => 'boolean',
            'trial_ends_at' => Day::class,
            'subscription_ends_at' => Day::class,
            'verify_requested_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    /** The currency a company of this kind pays in: AFN for Afghan companies, USD for NGOs and international clients. */
    public static function currencyFor(string $kind): string
    {
        return self::KINDS[$kind][1] ?? 'USD';
    }

    /** A free address for the public profile: "acme-construction", then "acme-construction-2" ... */
    public static function freeSlug(string $name, ?int $except = null): string
    {
        $base = Str::slug($name) ?: 'company';
        $slug = $base;
        for ($n = 2; static::where('slug', $slug)->when($except, fn (Builder $q) => $q->whereKeyNot($except))->exists(); $n++) {
            $slug = $base.'-'.$n;
        }

        return $slug;
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function industries(): BelongsToMany
    {
        return $this->belongsToMany(Industry::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    /** The tenders this buyer posted. */
    public function tenders(): HasMany
    {
        return $this->hasMany(Tender::class);
    }

    public function savedTenders(): BelongsToMany
    {
        return $this->belongsToMany(Tender::class, 'saved_tenders')->withPivot('created_at');
    }

    public function owner(): ?User
    {
        return $this->users()->where('role', User::OWNER)->orderBy('id')->first();
    }

    /** Companies shown in the public directory. */
    public function scopeListed(Builder $query): Builder
    {
        return $query->where('is_listed', true)->where('status', '!=', self::SUSPENDED);
    }

    /** The kind of buyer written on the tenders this organisation posts. */
    public function buyerType(): string
    {
        return ['government' => 'government', 'ngo' => 'ngo', 'company' => 'private', 'international' => 'private'][$this->kind] ?? 'private';
    }

    /**
     * May this account post tenders now? It must be a buyer. While posting is free for
     * everybody (Settings) that is enough; afterwards its plan must include posting.
     */
    public function canPostTenders(): bool
    {
        if (! $this->is_buyer || $this->status === self::SUSPENDED) {
            return false;
        }

        return \App\Support\Platform::bool('posting_free') || ($this->isUsable() && (bool) $this->plan?->can_post);
    }

    /** What a website address may be once https:// and www. are taken off: example.af, shop.example.com/en */
    public const DOMAIN = '/^(?=.{4,200}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,24}(\/[^\s]*)?$/i';

    /** "HTTPS://www.Example.af/" becomes "example.af"; empty stays null. */
    public static function websiteHost(?string $value): ?string
    {
        $value = trim((string) $value);
        $value = preg_replace('#^[a-z][a-z0-9+.-]*://#i', '', $value);
        $value = preg_replace('#^www\.#i', '', $value);
        $value = rtrim($value, '/');
        // the name of the site in small letters; a path after it stays as written
        $value = preg_replace_callback('#^[^/]+#', fn ($m) => strtolower($m[0]), $value);

        return $value === '' ? null : $value;
    }

    public function kindLabel(): string
    {
        return __(self::KINDS[$this->kind][0] ?? 'Company');
    }

    public function isVerified(): bool
    {
        return $this->verify_status === 'verified';
    }

    public function verifyLabel(): string
    {
        return __(self::VERIFY[$this->verify_status] ?? 'Not verified');
    }

    /** How much of the profile is filled in, in percent, and what is still missing. */
    public function completeness(): array
    {
        $checks = [
            'About the company' => filled($this->about),
            'Phone' => filled($this->phone),
            'Province' => filled($this->province_id),
            'Address' => filled($this->address),
            'Licence number' => filled($this->license_no),
            'Logo' => filled($this->logo_path),
            'Sectors' => $this->industries()->exists(),
            'Verification' => $this->isVerified(),
        ];
        $done = count(array_filter($checks));

        return ['percent' => (int) round(100 * $done / count($checks)), 'missing' => array_keys(array_filter($checks, fn (bool $ok) => ! $ok))];
    }

    /** The day the paid time (or the trial) runs until, or null when there is no end. */
    public function endsOn(): ?Carbon
    {
        return $this->status === self::TRIAL ? $this->trial_ends_at : $this->subscription_ends_at;
    }

    /** On the free plan: active, nothing to pay and no end date. */
    public function onFreePlan(): bool
    {
        return $this->status === self::ACTIVE && $this->subscription_ends_at === null && (bool) ($this->plan?->isFree() ?? true);
    }

    public function isUsable(): bool
    {
        if ($this->status === self::SUSPENDED) {
            return false;
        }
        $end = $this->endsOn();

        return $end === null || $end->toDateString() >= now($this->timezone ?: 'UTC')->toDateString();
    }

    /** May the people of this company read full tenders and download their documents? */
    public function hasFullAccess(): bool
    {
        return $this->isUsable() && (bool) $this->plan?->full_access;
    }

    public function hasInstantAlerts(): bool
    {
        return $this->isUsable() && (bool) $this->plan?->instant_alerts;
    }

    public function hasWhatsappAlerts(): bool
    {
        return $this->isUsable() && (bool) $this->plan?->whatsapp_alerts && $this->alert_whatsapp && filled($this->whatsapp);
    }

    /** Whole days of paid time left, 0 on the last day, below zero when it ended. Null when there is no end. */
    public function daysLeft(): ?int
    {
        $end = $this->endsOn();
        if (! $end) {
            return null;
        }
        $today = Carbon::parse(now($this->timezone ?: 'UTC')->toDateString());

        return (int) $today->diffInDays(Carbon::parse($end->toDateString()), false);
    }

    /**
     * The period that is running: when it started, when it ends, how many days it has, how many
     * are used and left, and the share used in percent.
     *
     * @return array{from: ?Carbon, to: ?Carbon, total: ?int, used: ?int, left: ?int, percent: ?int}
     */
    public function period(): array
    {
        $zone = $this->timezone ?: 'UTC';
        $to = $this->endsOn();
        $from = $this->status === self::TRIAL
            ? ($this->created_at ? Carbon::parse($this->created_at->copy()->timezone($zone)->toDateString()) : null)
            : Subscription::runningFor($this)?->starts_on;
        $left = $this->daysLeft();

        $total = $from && $to ? max(1, (int) $from->diffInDays(Carbon::parse($to->toDateString()), false)) : null;
        $used = $total !== null && $left !== null ? min($total, max(0, $total - max(0, $left))) : null;

        return [
            'from' => $from,
            'to' => $to,
            'total' => $total,
            'used' => $used,
            'left' => $left,
            'percent' => $total ? (int) round(100 * $used / $total) : null,
        ];
    }

    public function statusLabel(): string
    {
        if ($this->status === self::SUSPENDED) {
            return 'Suspended';
        }
        if (! $this->isUsable()) {
            return 'Expired';
        }

        return $this->status === self::TRIAL ? 'Trial' : 'Active';
    }

    /** Where the tender alerts go by email. */
    public function alertEmail(): ?string
    {
        return $this->notify_email ?: ($this->email ?: $this->owner()?->email);
    }
}
