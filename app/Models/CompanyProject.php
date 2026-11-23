<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A project a company carried out (or is carrying out), shown in its portfolio.
 *
 * Two things are kept apart: whether the company published it (status), and what Tenders.af
 * checked about it (review_status). Uploading a document never marks a project as reviewed;
 * only an administrator does, after looking at the evidence.
 */
class CompanyProject extends Model
{
    public const DRAFT = 'draft';

    public const PUBLISHED = 'published';

    public const PROGRESS = ['completed' => 'Completed', 'ongoing' => 'Ongoing'];

    /** review status => [label, what it means] */
    public const REVIEW = [
        'submitted' => ['Company submitted', 'Written by the company. Not checked by Tenders.af.'],
        'pending' => ['Pending review', 'The company sent evidence; Tenders.af has not finished checking it.'],
        'evidence_reviewed' => ['Evidence reviewed', 'Tenders.af looked at documents the company sent for this project.'],
        'client_confirmed' => ['Client reference confirmed', 'The client confirmed this project to Tenders.af.'],
        'rejected' => ['Not accepted', 'Tenders.af could not confirm this project from the evidence sent.'],
    ];

    public const MAX_IMAGES = 8;

    public const MAX_EVIDENCE = 5;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'client_public' => 'boolean',
            'value_public' => 'boolean',
            'is_featured' => 'boolean',
            'started_on' => 'date',
            'completed_on' => 'date',
            'reviewed_at' => 'datetime',
            'contract_value' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function industry(): BelongsTo
    {
        return $this->belongsTo(Industry::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(CompanyProjectFile::class)->orderBy('sort')->orderBy('id');
    }

    public function images(): HasMany
    {
        return $this->files()->where('kind', 'image');
    }

    public function evidence(): HasMany
    {
        return $this->files()->where('kind', 'evidence');
    }

    /** Projects the public may see: published by a company that is listed and not suspended. */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('status', self::PUBLISHED)->where('review_status', '!=', 'rejected')
            ->whereHas('company', fn (Builder $q) => $q->listed());
    }

    public static function freeSlug(int $companyId, string $title, ?int $except = null): string
    {
        $base = Str::slug($title) ?: 'project';
        $slug = $base;
        for ($n = 2; static::where('company_id', $companyId)->where('slug', $slug)->when($except, fn (Builder $q) => $q->whereKeyNot($except))->exists(); $n++) {
            $slug = $base.'-'.$n;
        }

        return $slug;
    }

    public function reviewLabel(): string
    {
        return __(self::REVIEW[$this->review_status][0] ?? 'Company submitted');
    }

    public function reviewMeaning(): string
    {
        return __(self::REVIEW[$this->review_status][1] ?? self::REVIEW['submitted'][1]);
    }

    /** Has Tenders.af checked something about this project? */
    public function isReviewed(): bool
    {
        return in_array($this->review_status, ['evidence_reviewed', 'client_confirmed'], true);
    }

    public function period(): string
    {
        $from = $this->started_on?->format('M Y');
        $to = $this->progress === 'ongoing' ? __('ongoing') : $this->completed_on?->format('M Y');

        return collect([$from, $to])->filter()->join(' – ');
    }

    /** The client's name, only when the company may disclose it. */
    public function publicClient(): ?string
    {
        return $this->client_public && filled($this->client_name) ? $this->client_name : null;
    }

    public function publicValue(): ?string
    {
        return $this->value_public && $this->contract_value !== null ? money($this->contract_value, $this->contract_currency) : null;
    }
}
