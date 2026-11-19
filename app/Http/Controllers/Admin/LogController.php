<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AdminLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** The activity log: what each administrator did, newest first. */
class LogController extends Controller
{
    public function index(Request $request): View
    {
        $term = trim((string) $request->query('q', ''));
        $adminId = $request->query('admin');

        $rows = AdminLog::query()
            ->when($term !== '', fn (Builder $q) => $q->where(fn (Builder $inner) => $inner
                ->where('action', 'like', "%$term%")->orWhere('subject', 'like', "%$term%")->orWhere('details', 'like', "%$term%")))
            ->when($adminId, fn (Builder $q) => $q->where('admin_id', $adminId))
            ->when($request->query('from'), fn (Builder $q, $date) => $q->where('created_at', '>=', $date.' 00:00:00'))
            ->when($request->query('to'), fn (Builder $q, $date) => $q->where('created_at', '<=', $date.' 23:59:59'))
            ->orderByDesc('id')->paginate(50)->withQueryString();

        return view('admin.logs', [
            'rows' => $rows,
            'term' => $term,
            'adminId' => $adminId,
            'from' => $request->query('from'),
            'to' => $request->query('to'),
            'admins' => Admin::orderBy('name')->pluck('name', 'id')->all(),
        ]);
    }
}
