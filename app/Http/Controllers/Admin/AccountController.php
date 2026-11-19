<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\CrudController;
use App\Models\PlatformAccount;
use App\Models\PlatformExpense;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Financial accounts of the platform: where the money of subscriptions is received. */
class AccountController extends CrudController
{
    public const TYPES = ['bank' => 'Bank account', 'cash' => 'Cash', 'online' => 'Online payment', 'mobile' => 'Mobile money', 'crypto' => 'USDT / crypto wallet', 'other' => 'Other'];

    protected string $model = PlatformAccount::class;

    protected string $route = 'admin.accounts';

    protected string $layout = 'layouts.admin';

    protected string $title = 'Payment methods';

    protected string $singular = 'Payment method';

    protected string $icon = 'building-columns';

    protected function query(Request $request): Builder
    {
        return PlatformAccount::query()
            ->withSum(['subscriptions as received' => fn ($q) => $q->whereNotNull('paid_on')], 'amount')
            ->withSum(['subscriptions as received_month' => fn ($q) => $q->whereNotNull('paid_on')->where('paid_on', '>=', now()->startOfMonth()->toDateString())], 'amount')
            ->withCount(['subscriptions as payments' => fn ($q) => $q->whereNotNull('paid_on')]);
    }

    protected function fields(?Model $record): array
    {
        return [
            ['name' => 'name', 'label' => 'Name', 'rules' => ['required', 'string', 'max:150']],
            ['name' => 'type', 'label' => 'Kind', 'type' => 'select', 'default' => 'bank', 'options' => array_map('__', self::TYPES), 'rules' => ['required', Rule::in(array_keys(self::TYPES))]],
            ['name' => 'currency', 'label' => 'Currency', 'default' => 'USD', 'rules' => ['required', 'alpha', 'size:3'], 'help' => 'Three letters, for example USD'],
            ['name' => 'number', 'label' => 'Account or wallet number', 'rules' => ['nullable', 'string', 'max:100'], 'help' => 'For example the HesabPay number, a Binance Pay ID, or a USDT address. Companies see it and can copy it. For USDT, write the network (TRC20, BEP20 ...) in the details.'],
            ['name' => 'qr', 'label' => 'QR code to scan', 'type' => 'file', 'virtual' => true, 'accept' => 'image/*', 'rules' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
                'help' => $record?->qr_path ? 'A QR code is already saved. Choose another picture only to replace it.' : 'Optional picture. Companies scan it on their Subscription page.'],
            ['name' => 'is_active', 'label' => 'In use', 'type' => 'checkbox', 'default' => true, 'help' => 'Accounts in use are shown to companies as the ways to pay.'],
            ['name' => 'details', 'label' => 'Details', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:2000'], 'col' => 12, 'help' => 'Companies see this on their Subscription page as the way to pay you: write the bank name, account name and number, or the wallet number.'],
        ];
    }

    protected function columns(): array
    {
        return [
            'Name' => fn (PlatformAccount $a) => new HtmlString('<a class="fw-semibold text-decoration-none" href="'.e(route('admin.accounts.show', ['id' => $a->id])).'">'.e($a->name).'</a>'),
            'Kind' => fn (PlatformAccount $a) => __(self::TYPES[$a->type] ?? $a->type),
            'Account or wallet number' => fn (PlatformAccount $a) => $a->number,
            'QR code' => fn (PlatformAccount $a) => $a->qr_path ? __('Yes') : __('No'),
            'Payments' => fn (PlatformAccount $a) => $a->payments,
            'This month' => fn (PlatformAccount $a) => money($a->received_month ?? 0, $a->currency),
            'Received' => fn (PlatformAccount $a) => money($a->received ?? 0, $a->currency),
            'In use' => fn (PlatformAccount $a) => $a->is_active ? __('Yes') : __('No'),
        ];
    }

    protected function beforeSave(array $data, ?Model $record, Request $request): array
    {
        $data['currency'] = strtoupper($data['currency']);

        return $data;
    }

    protected function rowLinks(Model $record): array
    {
        return [['label' => __('Statement'), 'icon' => 'list', 'url' => route('admin.accounts.show', ['id' => $record->id])]];
    }

    /** Confirmed payments that were not put in any account: the list says where to fix them. */
    protected function headerLinks(Request $request): array
    {
        $loose = Subscription::whereNotNull('paid_on')->whereNull('platform_account_id')->count();

        return $loose ? [['label' => trans_choice('{1} 1 payment without an account|[2,*] :count payments without an account', $loose, ['count' => $loose]), 'url' => route('admin.subscriptions.index', ['account' => 'none'])]] : [];
    }

    public const PERIODS = ['month' => 'This month', 'last_month' => 'Last month', 'year' => 'This year', 'last_year' => 'Last year', 'all' => 'All time'];

    /** The statement of an account: every payment received in it, who paid, when and for what. */
    public function show(Request $request, int $id): View|StreamedResponse
    {
        $account = PlatformAccount::findOrFail($id);

        // the period: one of the quick choices, or two dates typed by hand
        $period = $request->query('period');
        $from = $request->query('from');
        $to = $request->query('to');
        if ($from || $to) {
            $period = 'custom';
        } else {
            $period = isset(self::PERIODS[$period]) ? $period : 'all';
            [$from, $to] = match ($period) {
                'month' => [now()->startOfMonth()->toDateString(), null],
                'last_month' => [now()->subMonthNoOverflow()->startOfMonth()->toDateString(), now()->subMonthNoOverflow()->endOfMonth()->toDateString()],
                'year' => [now()->startOfYear()->toDateString(), null],
                'last_year' => [now()->subYear()->startOfYear()->toDateString(), now()->subYear()->endOfYear()->toDateString()],
                default => [null, null],
            };
        }
        $term = trim((string) $request->query('q', ''));

        $paid = fn () => Subscription::where('platform_account_id', $account->id)->whereNotNull('paid_on');
        $sum = fn ($query) => $query->selectRaw('currency, sum(amount) as total')->groupBy('currency')->orderBy('currency')->pluck('total', 'currency')->all();
        $filtered = fn () => $paid()
            ->when($from, fn (Builder $q) => $q->where('paid_on', '>=', $from))
            ->when($to, fn (Builder $q) => $q->where('paid_on', '<=', $to))
            ->when($term !== '', fn (Builder $q) => $q->where(fn (Builder $inner) => $inner
                ->where('reference', 'like', "%$term%")
                ->orWhereHas('company', fn (Builder $company) => $company->where('name', 'like', "%$term%")->orWhere('email', 'like', "%$term%"))));

        if ($request->query('export') === 'csv') {
            return $this->export($account, $filtered()->with('company', 'plan')->orderBy('paid_on')->orderBy('id')->get());
        }

        // the last twelve months in the currency of the account, oldest first, for the bars
        $chart = [];
        for ($i = 11; $i >= 0; $i--) {
            $chart[now()->startOfMonth()->subMonths($i)->format('Y-m')] = 0.0;
        }
        $since = now()->startOfMonth()->subMonths(11)->toDateString();
        foreach ($paid()->where('paid_on', '>=', $since)->where('currency', $account->currency)->get(['paid_on', 'amount']) as $row) {
            $key = $row->paid_on->format('Y-m');
            if (isset($chart[$key])) {
                $chart[$key] += (float) $row->amount;
            }
        }

        return view('admin.account', [
            'account' => $account,
            'periods' => self::PERIODS,
            'period' => $period,
            'from' => $from,
            'to' => $to,
            'term' => $term,
            'month' => $sum($paid()->where('paid_on', '>=', now()->startOfMonth()->toDateString())),
            'year' => $sum($paid()->where('paid_on', '>=', now()->startOfYear()->toDateString())),
            'all' => $sum($paid()),
            'count' => $paid()->count(),
            // what went out of this account (platform expenses), and what is left of each currency
            'spent' => $spent = PlatformExpense::where('platform_account_id', $account->id)->selectRaw('currency, sum(amount) as total')->groupBy('currency')->orderBy('currency')->pluck('total', 'currency')->all(),
            'balance' => $this->balance($sum($paid()), $spent),
            'expenses' => PlatformExpense::where('platform_account_id', $account->id)->orderByDesc('date')->orderByDesc('id')->limit(6)->get(),
            'last' => $paid()->with('company')->orderByDesc('paid_on')->orderByDesc('id')->first(),
            'chart' => $chart,
            'filtered' => $sum($filtered()),
            'filteredCount' => $filtered()->count(),
            'rows' => $filtered()->with('company', 'plan')->orderByDesc('paid_on')->orderByDesc('id')->paginate(30)->withQueryString(),
            'payers' => $paid()->with('company')->selectRaw('company_id, currency, sum(amount) as total, count(*) as payments')
                ->groupBy('company_id', 'currency')->orderByDesc('total')->limit(6)->get(),
        ]);
    }

    /** Received minus spent, currency by currency. */
    private function balance(array $received, array $spent): array
    {
        $balance = [];
        foreach (array_unique(array_merge(array_keys($received), array_keys($spent))) as $currency) {
            $balance[$currency] = (float) ($received[$currency] ?? 0) - (float) ($spent[$currency] ?? 0);
        }

        return $balance;
    }

    /** The payments on the screen as a file for Excel. */
    private function export(PlatformAccount $account, $rows): StreamedResponse
    {
        $name = Str::slug($account->name).'-statement-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($rows, $account) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // so Excel reads names in any language
            fputcsv($out, ['Paid on', 'Company', 'Email', 'Plan', 'From', 'Until', 'Transfer number', 'Amount', 'Currency', 'Account']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row->paid_on?->toDateString(), $row->company?->name, $row->company?->email, $row->plan?->name,
                    $row->starts_on?->toDateString(), $row->ends_on?->toDateString(), $row->reference,
                    number_format((float) $row->amount, 2, '.', ''), $row->currency, $account->name,
                ]);
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** The QR code of the account, as the companies see it. */
    public function qr(int $id): StreamedResponse
    {
        $account = PlatformAccount::findOrFail($id);
        abort_unless($account->qr_path && Storage::disk('local')->exists($account->qr_path), 404);

        return Storage::disk('local')->response($account->qr_path);
    }

    protected function afterSave(Model $record, array $virtual, Request $request, bool $created): void
    {
        if ($file = $request->file('qr')) {
            if ($record->qr_path) {
                Storage::disk('local')->delete($record->qr_path);
            }
            $record->update(['qr_path' => $file->store('accounts', 'local')]);
        }
    }

    protected function deleteProblem(Model $record): ?string
    {
        return $record->subscriptions()->exists() ? __('Payments were received in this account. Switch it off instead.') : null;
    }
}
