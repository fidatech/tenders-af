<?php

namespace App\Models;

use App\Casts\Day;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * One tender. Everybody may read its summary; the description, the eligibility rules, the
 * submission details, the contact and the documents are for companies on a paid plan.
 * Dates and the deadline are in Kabul time.
 */
class Tender extends Model
{
    public const DRAFT = 'draft';

    public const PUBLISHED = 'published';

    public const CANCELLED = 'cancelled';

    public const AWARDED = 'awarded';

    public const PENDING = 'pending';

    public const REJECTED = 'rejected';

    // pending: posted by a buyer and waiting for an administrator; rejected: sent back to the buyer
    public const STATUSES = ['draft' => 'Draft', 'pending' => 'Waiting for approval', 'published' => 'Published', 'rejected' => 'Sent back', 'cancelled' => 'Cancelled', 'awarded' => 'Awarded'];

    public const ZONE = 'Asia/Kabul';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'published_on' => Day::class,
            'questions_until' => Day::class,
            'deadline' => 'datetime',
            'alerted_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    public function industry(): BelongsTo
    {
        return $this->belongsTo(Industry::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    /** The buyer that posted the tender; null when the data team entered it. */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(TenderDocument::class);
    }

    /** The clock of every deadline. */
    public static function now(): Carbon
    {
        return now(self::ZONE);
    }

    /** A free address for the tender page, made from its title. */
    public static function freeSlug(string $title, ?int $except = null): string
    {
        $base = Str::limit(Str::slug($title) ?: 'tender', 90, '');
        $slug = $base;
        for ($n = 2; static::where('slug', $slug)->when($except, fn (Builder $q) => $q->whereKeyNot($except))->exists(); $n++) {
            $slug = $base.'-'.$n;
        }

        return $slug;
    }

    /** Tenders the public may see: published, awarded or cancelled (never drafts). */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->whereIn('status', [self::PUBLISHED, self::AWARDED, self::CANCELLED]);
    }

    /** Published and the deadline has not passed (or there is none). */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::PUBLISHED)
            ->where(fn (Builder $q) => $q->whereNull('deadline')->orWhere('deadline', '>=', self::now()->toDateTimeString()));
    }

    /** Open, and closing within this many days. */
    public function scopeClosingWithin(Builder $query, int $days): Builder
    {
        return $query->open()->whereNotNull('deadline')->where('deadline', '<=', self::now()->addDays($days)->toDateTimeString());
    }

    /** The search box and the pick lists of a tender list: q, industry, category, province, type, buyer. */
    public function scopeFiltered(Builder $query, array $filters): Builder
    {
        $term = trim((string) ($filters['q'] ?? ''));

        return $query
            ->when($term !== '', fn (Builder $q) => $q->where(fn (Builder $inner) => $inner
                ->where('title', 'like', "%$term%")->orWhere('buyer_name', 'like', "%$term%")
                ->orWhere('reference', 'like', "%$term%")->orWhere('summary', 'like', "%$term%")))
            ->when($filters['industry'] ?? null, fn (Builder $q, $id) => $q->where('industry_id', $id))
            ->when($filters['category'] ?? null, fn (Builder $q, $id) => $q->where('category_id', $id))
            ->when($filters['province'] ?? null, fn (Builder $q, $id) => $q->where('province_id', $id))
            ->when($filters['type'] ?? null, fn (Builder $q, $type) => $q->where('type', $type))
            ->when($filters['buyer'] ?? null, fn (Builder $q, $buyer) => $q->where('buyer_type', $buyer));
    }

    /** Tenders this company wants to hear about: its sectors (or categories) and its tender types. */
    public function scopeMatching(Builder $query, Company $company): Builder
    {
        $industries = $company->industries()->pluck('industries.id')->all();
        $categories = $company->categories()->pluck('categories.id')->all();
        $types = array_values(array_filter($company->alert_types ?? []));

        if (! $industries && ! $categories) {
            return $query->whereRaw('1 = 0'); // nothing chosen: nothing matches
        }

        return $query
            ->where(fn (Builder $q) => $q->whereIn('industry_id', $industries ?: [0])->orWhereIn('category_id', $categories ?: [0]))
            ->when($types, fn (Builder $q) => $q->whereIn('type', $types));
    }

    /** May the buyer still change everything? Only before the tender is published. */
    public function isEditableByBuyer(): bool
    {
        return in_array($this->status, [self::DRAFT, self::PENDING, self::REJECTED], true);
    }

    public function isOpen(): bool
    {
        return $this->status === self::PUBLISHED && ($this->deadline === null || $this->deadline->gte(self::now()));
    }

    /** Open, Closing soon, Closed, Awarded, Cancelled or Draft: what a reader needs to know first. */
    public function state(): string
    {
        return match (true) {
            $this->status === self::DRAFT => 'draft',
            $this->status === self::PENDING => 'pending',
            $this->status === self::REJECTED => 'rejected',
            $this->status === self::CANCELLED => 'cancelled',
            $this->status === self::AWARDED => 'awarded',
            ! $this->isOpen() => 'closed',
            $this->deadline !== null && $this->deadline->lte(self::now()->addDays(3)) => 'soon',
            default => 'open',
        };
    }

    public function stateLabel(): string
    {
        return __(['draft' => 'Draft', 'pending' => 'Waiting for approval', 'rejected' => 'Sent back', 'cancelled' => 'Cancelled', 'awarded' => 'Awarded', 'closed' => 'Closed', 'soon' => 'Closing soon', 'open' => 'Open'][$this->state()]);
    }

    /** "2 days left", "5 hours left", "Closed" ... */
    public function timeLeft(): string
    {
        if ($this->status === self::AWARDED) {
            return __('Winner published');
        }
        if ($this->status === self::CANCELLED) {
            return __('Cancelled');
        }
        if ($this->deadline === null) {
            return __('No deadline given');
        }
        $now = self::now();
        if ($this->deadline->lt($now)) {
            return __('Closed');
        }
        $hours = (int) $now->diffInHours($this->deadline);
        if ($hours < 24) {
            return $hours < 1 ? __('Less than an hour left') : trans_choice('{1} 1 hour left|[2,*] :count hours left', $hours, ['count' => $hours]);
        }
        $days = (int) floor($hours / 24);

        return trans_choice('{1} 1 day left|[2,*] :count days left', $days, ['count' => $days]);
    }

    public function typeLabel(): string
    {
        return config('tenders.types')[$this->type][0] ?? strtoupper((string) $this->type);
    }

    public function typeName(): string
    {
        return __(config('tenders.types')[$this->type][1] ?? $this->type);
    }

    public function buyerTypeLabel(): string
    {
        return __(config('tenders.buyer_types')[$this->buyer_type] ?? $this->buyer_type);
    }

    public function submissionLabel(): ?string
    {
        return $this->submission_method ? __(config('tenders.submission_methods')[$this->submission_method] ?? $this->submission_method) : null;
    }

    /** "TA-000412": the number of the tender on this site. */
    public function number(): string
    {
        return 'TA-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    public function placeText(): string
    {
        return $this->province?->name ?? ($this->location ?: __('Several provinces'));
    }

    /** "Not disclosed", "1,200,000 AFN" or "500,000 to 800,000 AFN". */
    public function budgetText(): string
    {
        $currency = $this->budget_currency ?: 'AFN';
        $n = fn ($amount) => number_format((float) $amount);

        return match (true) {
            $this->budget_type === 'exact' && $this->budget_min !== null => $n($this->budget_min).' '.$currency,
            $this->budget_type === 'range' && $this->budget_min !== null && $this->budget_max !== null => $n($this->budget_min).' '.__('to').' '.$n($this->budget_max).' '.$currency,
            default => __('Not disclosed'),
        };
    }

    public function sizeLabel(): ?string
    {
        return $this->size_band ? __(config('tenders.size_bands')[$this->size_band] ?? $this->size_band) : null;
    }
}
