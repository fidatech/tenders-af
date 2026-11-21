<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\CrudController;
use App\Models\PlatformAccount;
use App\Models\PlatformExpense;
use App\Models\OfficeEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** The platform's own costs: server, domain, salaries ... With them the reports show profit, not only income. */
class ExpenseController extends CrudController
{
    protected string $model = PlatformExpense::class;

    protected string $route = 'admin.expenses';

    protected string $layout = 'layouts.admin';

    protected string $title = 'Expenses';

    protected string $singular = 'Expense';

    protected string $icon = 'money-bill-wave';

    protected array $search = ['description'];

    protected array $with = ['account'];

    protected string $orderBy = 'id';

    protected string $orderDirection = 'desc';

    protected function query(Request $request): Builder
    {
        return $this->filtered($request)->orderByDesc('date');
    }

    private function filtered(Request $request): Builder
    {
        return PlatformExpense::query()
            ->when($request->query('category'), fn (Builder $q, $key) => $q->where('category', $key))
            ->when($request->query('account'), fn (Builder $q, $id) => $q->where('platform_account_id', $id))
            ->when($request->query('currency'), fn (Builder $q, $code) => $q->where('currency', strtoupper($code)))
            ->when($request->query('from'), fn (Builder $q, $date) => $q->where('date', '>=', $date))
            ->when($request->query('to'), fn (Builder $q, $date) => $q->where('date', '<=', $date));
    }

    protected function filters(Request $request): array
    {
        return [
            ['name' => 'category', 'label' => __('Category'), 'options' => array_map('__', PlatformExpense::CATEGORIES), 'value' => $request->query('category')],
            ['name' => 'account', 'label' => __('Paid from'), 'options' => PlatformAccount::orderBy('name')->pluck('name', 'id')->all(), 'value' => $request->query('account')],
            ['name' => 'currency', 'label' => __('Currency'), 'options' => PlatformExpense::query()->distinct()->orderBy('currency')->pluck('currency', 'currency')->all(), 'value' => strtoupper((string) $request->query('currency'))],
            ['name' => 'from', 'label' => __('From'), 'type' => 'date', 'value' => $request->query('from')],
            ['name' => 'to', 'label' => __('To'), 'type' => 'date', 'value' => $request->query('to')],
        ];
    }

    protected function totals(Request $request): array
    {
        $totals = $this->filtered($request)->selectRaw('currency, sum(amount) as total')->groupBy('currency')->orderBy('currency')->pluck('total', 'currency')->all();

        return $totals ? ['Total' => sums($totals)] : [];
    }

    protected function headerLinks(Request $request): array
    {
        return [['label' => __('Profit report'), 'url' => route('admin.reports.index')]];
    }

    protected function fields(?Model $record): array
    {
        $accounts = PlatformAccount::orderBy('name')->get()->mapWithKeys(fn (PlatformAccount $a) => [$a->id => $a->name.' ('.$a->currency.')'])->all();

        return [
            ['name' => 'date', 'label' => 'Date', 'type' => 'date', 'default' => now()->toDateString(), 'rules' => ['required', 'date']],
            ['name' => 'category', 'label' => 'Category', 'type' => 'select', 'options' => array_map('__', PlatformExpense::CATEGORIES), 'rules' => ['required', Rule::in(array_keys(PlatformExpense::CATEGORIES))]],
            ['name' => 'amount', 'label' => 'Amount', 'type' => 'decimal', 'rules' => ['required', 'numeric', 'gt:0']],
            ['name' => 'currency', 'label' => 'Currency', 'default' => 'USD', 'rules' => ['required', 'alpha', 'size:3']],
            ['name' => 'platform_account_id', 'label' => 'Paid from', 'type' => 'select', 'options' => $accounts, 'rules' => ['nullable', 'exists:platform_accounts,id'], 'help' => 'The account the money went out of. Its statement then shows the real balance.', 'quick' => ['New financial account', 'admin.accounts.create']],
            ['name' => 'description', 'label' => 'Description', 'rules' => ['nullable', 'string', 'max:255']],
        ];
    }

    protected function columns(): array
    {
        return [
            'Description' => fn (PlatformExpense $e) => $e->description ?: $e->categoryLabel(),
            'Date' => fn (PlatformExpense $e) => fmt_date($e->date),
            'Category' => fn (PlatformExpense $e) => $e->categoryLabel(),
            'Paid from' => fn (PlatformExpense $e) => $e->account?->name,
            'Amount' => fn (PlatformExpense $e) => money($e->amount, $e->currency),
        ];
    }

    protected function canEdit(Model $record): bool
    {
        return ! OfficeEntry::where('expense_id', $record->id)->exists();
    }

    protected function beforeSave(array $data, ?Model $record, Request $request): array
    {
        $data['currency'] = strtoupper($data['currency']);

        return $data;
    }

    protected function auditLabel(Model $record): string
    {
        return ($record->description ?: $record->categoryLabel()).' · '.money($record->amount, $record->currency);
    }
}
