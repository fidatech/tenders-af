<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminLog;
use App\Models\ModuleChange;
use App\Support\AdminAccess;
use App\Support\Modules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Admin > Module Management. Every administrator who may open the admin dashboard can look;
 * only a super administrator may change a module (AdminAccess: admin.modules.update).
 */
class ModuleController extends Controller
{
    public function index(): View
    {
        $modules = Modules::all();
        $history = ModuleChange::orderByDesc('id')->limit(40)->get();

        return view('admin.modules', [
            'modules' => $modules,
            'history' => $history,
            'canEdit' => AdminAccess::allows(auth('admin')->user(), 'admin.modules.update', false),
        ]);
    }

    public function update(Request $request, string $module): RedirectResponse
    {
        abort_unless(Modules::exists($module), 404);
        $definition = Modules::definition($module);
        abort_unless($definition['built'], 404);

        $data = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'public_nav' => ['nullable', 'boolean'],
            'dashboard_nav' => ['nullable', 'boolean'],
            'notifications' => ['nullable', 'boolean'],
            'access' => ['nullable', Rule::in(['all', 'paid'])],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $values = [
            'public_nav' => $request->boolean('public_nav'),
            'dashboard_nav' => $request->boolean('dashboard_nav'),
            'notifications' => $request->boolean('notifications'),
            'access' => $data['access'] ?? 'all',
        ];
        if (! $definition['locked']) {
            $values['enabled'] = $request->boolean('enabled');
        }

        $changes = Modules::save($module, $values, $data['note'] ?? null);
        if (! $changes) {
            return back()->with('info', __('Nothing was changed.'));
        }

        $summary = collect($changes)->map(fn ($pair, $option) => $option.': '.json_encode($pair[0]).' → '.json_encode($pair[1]))->join(', ');
        AdminLog::record('changed a module', $definition['name'], $summary);

        $message = array_key_exists('enabled', $changes)
            ? ($changes['enabled'][1] ? __(':module is switched on.', ['module' => __($definition['name'])]) : __(':module is switched off. Nothing in it was deleted.', ['module' => __($definition['name'])]))
            : __(':module saved.', ['module' => __($definition['name'])]);

        return redirect()->to(route('admin.modules.index').'#module-'.$module)->with('success', $message);
    }
}
