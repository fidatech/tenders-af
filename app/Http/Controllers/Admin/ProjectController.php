<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminLog;
use App\Models\CompanyProject;
use App\Models\CompanyProjectFile;
use App\Models\Notice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin > Company projects: the projects companies list, and the review of their evidence.
 * A reviewer records what was actually checked; nothing is marked reviewed by itself.
 */
class ProjectController extends Controller
{
    public function index(Request $request): View
    {
        $term = trim((string) $request->query('q', ''));
        $sort = in_array($request->query('sort'), ['newest', 'oldest', 'title'], true) ? $request->query('sort') : 'newest';
        $rows = CompanyProject::with('company:id,name,slug')->withCount('evidence')
            ->when($term !== '', fn (Builder $q) => $q->where(fn (Builder $in) => $in->where('title', 'like', "%$term%")->orWhereHas('company', fn (Builder $c) => $c->where('name', 'like', "%$term%"))))
            ->when($request->query('review'), fn (Builder $q, $review) => $q->where('review_status', $review))
            ->when($request->query('status'), fn (Builder $q, $status) => $q->where('status', $status))
            ->when($sort === 'newest', fn (Builder $q) => $q->orderByDesc('updated_at'))
            ->when($sort === 'oldest', fn (Builder $q) => $q->orderBy('updated_at'))
            ->when($sort === 'title', fn (Builder $q) => $q->orderBy('title'))
            ->paginate(25)->withQueryString();

        return view('admin.projects.index', [
            'rows' => $rows,
            'term' => $term,
            'sort' => $sort,
            'counts' => CompanyProject::selectRaw('review_status, count(*) as n')->groupBy('review_status')->pluck('n', 'review_status'),
        ]);
    }

    public function show(int $id): View
    {
        return view('admin.projects.show', ['project' => CompanyProject::with('company', 'files', 'industry')->findOrFail($id)]);
    }

    public function review(Request $request, int $id): RedirectResponse
    {
        $project = CompanyProject::with('company')->findOrFail($id);
        $data = $request->validate([
            'review_status' => ['required', Rule::in(array_keys(CompanyProject::REVIEW))],
            'review_note' => ['nullable', 'string', 'max:500', Rule::requiredIf(fn () => $request->input('review_status') === 'rejected')],
        ], ['review_note.required' => __('Write why the project could not be confirmed; the company reads it.')]);

        // "reviewed" needs something that was looked at
        if ($data['review_status'] === 'evidence_reviewed' && ! $project->evidence()->exists()) {
            return back()->withErrors(['review_status' => __('There are no documents to review for this project.')]);
        }

        $project->update($data + ['reviewed_by' => auth('admin')->id(), 'reviewed_at' => now()]);
        AdminLog::record('reviewed a company project', $project->company->name.': '.$project->title, CompanyProject::REVIEW[$data['review_status']][0].($data['review_note'] ? ' · '.$data['review_note'] : ''));

        foreach ($project->company->users()->where('is_active', true)->pluck('id') as $userId) {
            Notice::create([
                'company_id' => $project->company_id,
                'user_id' => $userId,
                'title' => __('Project review: :status', ['status' => $project->reviewLabel()]),
                'body' => $project->title.($data['review_note'] ? ' · '.$data['review_note'] : ''),
                'url' => route('projects.edit', ['id' => $project->id]),
            ]);
        }

        return back()->with('success', __('The review is saved and the company is told.'));
    }

    public function file(int $id, int $file): StreamedResponse
    {
        $row = CompanyProjectFile::where('company_project_id', $id)->findOrFail($file);
        abort_unless(Storage::disk('local')->exists($row->path), 404);

        return Storage::disk('local')->response($row->path, $row->name, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
