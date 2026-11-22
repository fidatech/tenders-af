<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A private thread between two companies (Business Enquiries). */
class Enquiry extends Model
{
    /** How many new enquiries one company may start in a day. */
    public const DAILY_LIMIT = 20;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime', 'from_read_at' => 'datetime', 'to_read_at' => 'datetime', 'reported_at' => 'datetime'];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'from_company_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'to_company_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(EnquiryMessage::class)->orderBy('id');
    }

    /** The threads a company takes part in. */
    public function scopeFor(Builder $query, int $companyId): Builder
    {
        return $query->where(fn (Builder $q) => $q->where('from_company_id', $companyId)->orWhere('to_company_id', $companyId));
    }

    /** Threads with a message this company has not read: a new message clears the other side's read mark. */
    public function scopeUnreadFor(Builder $query, int $companyId): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->where(fn (Builder $in) => $in->where('to_company_id', $companyId)->whereNull('to_read_at'))
            ->orWhere(fn (Builder $out) => $out->where('from_company_id', $companyId)->whereNull('from_read_at')));
    }

    public function isReceiver(int $companyId): bool
    {
        return $this->to_company_id === $companyId;
    }

    /** The other company of the thread, seen from this one. */
    public function otherSide(int $companyId): ?Company
    {
        return $this->isReceiver($companyId) ? $this->sender : $this->receiver;
    }

    public function markRead(int $companyId): void
    {
        $this->forceFill([$this->isReceiver($companyId) ? 'to_read_at' : 'from_read_at' => now()])->saveQuietly();
    }
}
