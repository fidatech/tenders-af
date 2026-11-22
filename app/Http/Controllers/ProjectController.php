<?php

namespace App\Http\Controllers;

use App\Exceptions\UserError;
use App\Models\AdminNotice;
use App\Models\CompanyProject;
use App\Models\CompanyProjectFile;
use App\Models\Industry;
use App\Support\Modules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The projects of a company (Company Project Portfolios module). Only the owner of the company
 * manages them; a company only ever sees and changes its own projects.
 */
class ProjectController extends Controller
{
    /** Fair use: a company keeps up to this many projects. */
    public const LIMIT = 200;

    public function index(): View
    {
        $company = company();

        return view('projects.index', [
            'rows' => CompanyProject::where('company_id', $company->id)->withCount(['images', 'evidence'])->orderByDesc('is_featured')->orderBy('sort')->orderByDesc('id')->paginate(25),
            'company' => $company,
        ]);
    }

    public function create(): View
    {
        return view('projects.form', ['project' => new CompanyProject(['progress' => 'completed', 'country' => company()->country ?: 'Afghanistan']), 'industries' => Industry::options()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $company = company();
        if (CompanyProject::where('company_id', $company->id)->count() >= self::LIMIT) {
            throw new UserError(__('A company may keep up to :n projects.', ['n' => self::LIMIT]));
        }
        $data = $this->validated($request, null);

        $project = DB::transaction(function () use ($company, $data, $request) {
            $project = CompanyProject::create($data + ['company_id' => $company->id, 'slug' => CompanyProject::freeSlug($company->id, $data['title'])]);
            $this->files($project, $request);

            return $project;
        });

        return redirect()->route('projects.edit', ['id' => $project->id])->with('success', $project->status === CompanyProject::PUBLISHED ? __('The project is published on your page.') : __('The project is saved as a draft.'));
    }

    public function edit(int $id): View
    {
        return view('projects.form', ['project' => $this->mine($id)->load('files'), 'industries' => Industry::options()]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $project = $this->mine($id);
        $data = $this->validated($request, $project);
        if ($data['title'] !== $project->title) {
            $data['slug'] = CompanyProject::freeSlug($project->company_id, $data['title'], $project->id);
        }
        // what was checked was the project as it was: a change of its facts needs a new look
        $facts = ['title', 'client_name', 'started_on', 'completed_on', 'contract_value', 'scope', 'location'];
        if ($project->isReviewed() && collect($facts)->contains(fn ($f) => (string) $project->{$f} !== (string) ($data[$f] ?? null) && ! ($project->{$f} instanceof \DateTimeInterface && $project->{$f}->format('Y-m-d') === ($data[$f] ?? null)))) {
            $data['review_status'] = 'submitted';
            $data['review_note'] = null;
            $data['reviewed_at'] = null;
            $data['reviewed_by'] = null;
        }

        DB::transaction(function () use ($project, $data, $request) {
            $project->update($data);
            $this->files($project, $request);
        });

        return back()->with('success', __('The project is saved.'));
    }

    public function destroy(int $id): RedirectResponse
    {
        $project = $this->mine($id)->load('files');
        foreach ($project->files as $file) {
            Storage::disk('local')->delete($file->path);
        }
        $project->delete();

        return redirect()->route('projects.index')->with('success', __('The project is deleted.'));
    }

    public function destroyFile(int $id, int $file): RedirectResponse
    {
        $project = $this->mine($id);
        $row = CompanyProjectFile::where('company_project_id', $project->id)->findOrFail($file);
        Storage::disk('local')->delete($row->path);
        $row->delete();

        return back()->with('success', __('The file is removed.'));
    }

    /** A file of one of the company's own projects (pictures and evidence). */
    public function file(int $id, int $file): StreamedResponse
    {
        $project = $this->mine($id);
        $row = CompanyProjectFile::where('company_project_id', $project->id)->findOrFail($file);
        abort_unless(Storage::disk('local')->exists($row->path), 404);

        return Storage::disk('local')->response($row->path, $row->name, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function mine(int $id): CompanyProject
    {
        return CompanyProject::where('company_id', company_id())->findOrFail($id);
    }

    private function validated(Request $request, ?CompanyProject $project): array
    {
        $images = $project ? $project->images()->count() : 0;
        $evidence = $project ? $project->evidence()->count() : 0;
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'industry_id' => ['nullable', Rule::exists('industries', 'id')->where('is_active', true)],
            'client_name' => ['nullable', 'string', 'max:200'],
            'client_public' => ['nullable', 'boolean'],
            'location' => ['nullable', 'string', 'max:200'],
            'country' => ['nullable', 'string', Rule::in(array_values(config('countries')))],
            'started_on' => ['nullable', 'date', 'before_or_equal:today'],
            'completed_on' => ['nullable', 'date', 'after_or_equal:started_on', 'before_or_equal:today'],
            'progress' => ['required', Rule::in(array_keys(CompanyProject::PROGRESS))],
            'contract_value' => ['nullable', 'numeric', 'min:0', 'max:99999999999999'],
            'contract_currency' => ['nullable', 'required_with:contract_value', Rule::in(['AFN', 'USD', 'EUR', 'PKR', 'IRR', 'CNY', 'TRY', 'AED', 'GBP'])],
            'value_public' => ['nullable', 'boolean'],
            'scope' => ['nullable', 'string', 'max:3000'],
            'responsibilities' => ['nullable', 'string', 'max:3000'],
            'is_featured' => ['nullable', 'boolean'],
            'action' => ['required', Rule::in(['draft', 'publish'])],
            'images' => ['nullable', 'array', 'max:'.max(0, CompanyProject::MAX_IMAGES - $images)],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'evidence' => ['nullable', 'array', 'max:'.max(0, CompanyProject::MAX_EVIDENCE - $evidence)],
            'evidence.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ], [
            'images.max' => __('A project may have up to :n pictures.', ['n' => CompanyProject::MAX_IMAGES]),
            'evidence.max' => __('A project may have up to :n documents.', ['n' => CompanyProject::MAX_EVIDENCE]),
        ]);

        $data['client_public'] = $request->boolean('client_public');
        $data['value_public'] = $request->boolean('value_public');
        $data['is_featured'] = $request->boolean('is_featured');
        $data['status'] = $data['action'] === 'publish' ? CompanyProject::PUBLISHED : CompanyProject::DRAFT;
        if ($data['progress'] === 'ongoing') {
            $data['completed_on'] = null;
        }
        unset($data['action'], $data['images'], $data['evidence']);

        return $data;
    }

    private function files(CompanyProject $project, Request $request): void
    {
        $sort = (int) $project->files()->max('sort');
        foreach ((array) $request->file('images', []) as $file) {
            $project->files()->create(['kind' => 'image', 'path' => $file->store('projects/'.$project->company_id, 'local'), 'name' => mb_substr($file->getClientOriginalName(), 0, 200), 'size' => $file->getSize(), 'sort' => ++$sort]);
        }
        $sent = false;
        foreach ((array) $request->file('evidence', []) as $file) {
            $project->files()->create(['kind' => 'evidence', 'path' => $file->store('project-evidence/'.$project->company_id, 'local'), 'name' => mb_substr($file->getClientOriginalName(), 0, 200), 'size' => $file->getSize(), 'sort' => ++$sort]);
            $sent = true;
        }
        // new evidence asks for a review; it does not make the project reviewed
        if ($sent && in_array($project->review_status ?? 'submitted', ['submitted', 'rejected'], true)) {
            $project->update(['review_status' => 'pending', 'review_note' => null]);
            if (Modules::notifies('portfolios')) {
                AdminNotice::toAll('verification', $project->company->name.' sent evidence for a project', $project->title, route('admin.projects.show', ['id' => $project->id]));
            }
        }
    }
}
