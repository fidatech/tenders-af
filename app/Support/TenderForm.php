<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Tender;
use App\Models\TenderDocument;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * What a tender form may send and how it is tidied before it is stored. Shared by the
 * administrators (who enter tenders themselves) and by buyers (who post their own).
 */
class TenderForm
{
    /** Files that can be added in one save, and files a tender may have in all. */
    public const MAX_PER_SAVE = 20;

    public const MAX_PER_TENDER = 50;

    /** The boxes both forms have. */
    public static function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:100'],
            'type' => ['required', Rule::in(array_keys(config('tenders.types')))],
            'industry_id' => ['required', Rule::exists('industries', 'id')],
            'category_id' => ['nullable', Rule::exists('categories', 'id')],
            'province_id' => ['nullable', Rule::exists('provinces', 'id')],
            'location' => ['nullable', 'string', 'max:255'],
            'summary' => ['required', 'string', 'max:2000'],
            'description' => ['nullable', 'string', 'max:60000'],
            'eligibility' => ['nullable', 'string', 'max:10000'],
            'submission_method' => ['nullable', Rule::in(array_keys(config('tenders.submission_methods')))],
            'submission_details' => ['nullable', 'string', 'max:5000'],
            'contact' => ['nullable', 'string', 'max:2000'],
            'budget_type' => ['required', Rule::in(['exact', 'range', 'undisclosed'])],
            'budget_min' => ['nullable', 'required_unless:budget_type,undisclosed', 'numeric', 'min:0'],
            'budget_max' => ['nullable', 'required_if:budget_type,range', 'numeric', 'gte:budget_min'],
            'budget_currency' => ['nullable', 'required_unless:budget_type,undisclosed', Rule::in(config('tenders.currencies'))],
            'questions_until' => ['nullable', 'date'],
            'deadline' => ['nullable', 'date'],
            'documents' => ['nullable', 'array', 'max:'.self::MAX_PER_SAVE],
            'documents.*' => ['file', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,csv,txt,zip,rar,jpg,jpeg,png,webp', 'max:20480'],
        ];
    }

    /** How the boxes are named in a message about a mistake. */
    public static function names(): array
    {
        return ['budget_min' => __('budget'), 'budget_max' => __('highest budget'), 'industry_id' => __('sector'), 'category_id' => __('category'), 'province_id' => __('province')];
    }

    /** Tidy what the form sent before it is stored. */
    public static function prepare(array $data, ?Tender $tender): array
    {
        unset($data['documents']);
        if ($data['budget_type'] === 'undisclosed') {
            $data['budget_min'] = $data['budget_max'] = $data['budget_currency'] = null;
        } elseif ($data['budget_type'] === 'exact') {
            $data['budget_max'] = null;
        }
        // the category must belong to the chosen sector
        if (! empty($data['category_id']) && ! Category::where('id', $data['category_id'])->where('industry_id', $data['industry_id'])->exists()) {
            $data['category_id'] = null;
        }
        // a tender gets its publishing day the first time it is published
        if (($data['status'] ?? null) === Tender::PUBLISHED && empty($data['published_on'])) {
            $data['published_on'] = $tender?->published_on?->toDateString() ?? Tender::now()->toDateString();
        }

        return $data;
    }

    public static function addDocuments(Tender $tender, Request $request): void
    {
        $files = $request->file('documents', []);
        if ($files && $tender->documents()->count() + count($files) > self::MAX_PER_TENDER) {
            throw new \App\Exceptions\UserError(__('A tender can have up to :count documents. Remove some, or put them in one zip file.', ['count' => self::MAX_PER_TENDER]));
        }
        foreach ($request->file('documents', []) as $file) {
            TenderDocument::create([
                'tender_id' => $tender->id,
                'name' => mb_substr($file->getClientOriginalName(), 0, 250),
                'path' => $file->store('tenders/'.$tender->id, 'local'),
                'size' => (int) $file->getSize(),
            ]);
        }
    }
}
