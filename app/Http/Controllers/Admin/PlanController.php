<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\CrudController;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlanController extends CrudController
{
    public const INTERVALS = ['monthly' => 'Monthly', 'yearly' => 'Yearly', 'lifetime' => 'Lifetime'];

    protected string $model = Plan::class;

    protected string $route = 'admin.plans';

    protected string $layout = 'layouts.admin';

    protected string $title = 'Plans';

    protected string $singular = 'Plan';

    protected string $icon = 'layer-group';

    protected string $orderBy = 'sort';

    protected function query(Request $request): Builder
    {
        return Plan::query()->withCount('companies');
    }

    protected function fields(?Model $record): array
    {
        return [
            ['name' => 'name', 'label' => 'Name', 'rules' => ['required', 'string', 'max:100']],
            ['name' => 'interval', 'label' => 'Paid', 'type' => 'select', 'default' => 'yearly', 'options' => array_map('__', self::INTERVALS), 'rules' => ['required', Rule::in(array_keys(self::INTERVALS))]],
            ['name' => 'price', 'label' => 'Price', 'type' => 'decimal', 'default' => 0, 'rules' => ['required', 'numeric', 'min:0']],
            ['name' => 'currency', 'label' => 'Currency', 'type' => 'select', 'default' => 'AFN', 'options' => ['AFN' => 'AFN: Afghan companies', 'USD' => 'USD: NGOs and international clients'], 'rules' => ['required', Rule::in(['AFN', 'USD'])], 'help' => 'Offer every paid plan twice: once in AFN and once in USD.'],
            ['name' => 'max_users', 'label' => 'Users allowed', 'type' => 'number', 'rules' => ['nullable', 'integer', 'min:1'], 'help' => 'Leave empty for no limit.'],
            ['name' => 'sort', 'label' => 'Order in lists', 'type' => 'number', 'default' => 0, 'rules' => ['required', 'integer', 'min:0', 'max:9999'], 'help' => 'New companies start on the free plan.'],
            ['name' => 'is_active', 'label' => 'In use', 'type' => 'checkbox', 'default' => true],
            ['name' => 'full_access', 'label' => 'Full tender details and documents', 'type' => 'checkbox', 'default' => true],
            ['name' => 'instant_alerts', 'label' => 'Email alert as soon as a tender is published', 'type' => 'checkbox', 'default' => true, 'help' => 'Off: only the weekly digest.'],
            ['name' => 'whatsapp_alerts', 'label' => 'WhatsApp alerts', 'type' => 'checkbox', 'default' => false],
            ['name' => 'can_post', 'label' => 'May post tenders', 'type' => 'checkbox', 'default' => false, 'help' => 'Only matters once posting is no longer free for everybody (Settings).'],
            ['name' => 'description', 'label' => 'Description', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:2000'], 'col' => 12],
        ];
    }

    protected function columns(): array
    {
        return [
            'Name' => fn (Plan $p) => $p->name,
            'Price' => fn (Plan $p) => money($p->price, $p->currency).' / '.__(self::INTERVALS[$p->interval] ?? $p->interval),
            'Full tenders' => fn (Plan $p) => $p->full_access ? __('Yes') : __('No'),
            'WhatsApp' => fn (Plan $p) => $p->whatsapp_alerts ? __('Yes') : __('No'),
            'Users allowed' => fn (Plan $p) => $p->max_users ?? __('No limit'),
            'Companies' => fn (Plan $p) => $p->companies_count,
            'In use' => fn (Plan $p) => $p->is_active ? __('Yes') : __('No'),
        ];
    }

    protected function beforeSave(array $data, ?Model $record, Request $request): array
    {
        $data['currency'] = strtoupper($data['currency']);

        return $data;
    }

    protected function deleteProblem(Model $record): ?string
    {
        return $record->companies()->exists() ? __('Companies are on this plan. Switch it off instead.') : null;
    }
}
