<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\CrudController;
use App\Models\Plan;
use App\Models\OfficeEntry;
use App\Exceptions\UserError;
use Illuminate\Support\Facades\DB;
use App\Models\PlatformAccount;
use App\Models\Subscription;
use App\Models\AdminLog;
use App\Support\Lifecycle;
use App\Models\Company;
use App\Models\User;
use App\Models\Notice;
use App\Mail\PaymentRejected;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Subscriptions: each row is a period a company pays for. Saving one puts the company on
 * that plan and lets it work until the end date of its latest subscription.
 */
class SubscriptionController extends CrudController
{
    protected string $model = Subscription::class;

    protected string $route = 'admin.subscriptions';

    protected string $layout = 'layouts.admin';

    protected string $title = 'Subscriptions';

    protected string $singular = 'Subscription';

    protected string $icon = 'receipt';

    protected array $search = ['note', 'reference', 'company.name'];

    protected array $with = ['company', 'plan', 'account'];

    protected string $orderBy = 'id';

    protected string $orderDirection = 'desc';

    protected function query(Request $request): Builder
    {
        return Subscription::query()
            ->when($request->query('company'), fn (Builder $q, $id) => $q->where('company_id', $id))
            ->when($request->query('currency'), fn (Builder $q, $code) => $q->where('currency', strtoupper($code)))
            ->when($request->query('account') === 'none', fn (Builder $q) => $q->whereNotNull('paid_on')->whereNull('platform_account_id'))
            ->when(is_numeric($request->query('account')), fn (Builder $q) => $q->where('platform_account_id', (int) $request->query('account')))
            ->when($request->query('paid') === 'no', fn (Builder $q) => $q->whereNull('paid_on')->whereNull('rejected_at'))
            ->when($request->query('paid') === 'rejected', fn (Builder $q) => $q->whereNull('paid_on')->whereNotNull('rejected_at'))
            ->when($request->query('paid') === 'yes', fn (Builder $q) => $q->whereNotNull('paid_on'));
    }

    protected function filters(Request $request): array
    {
        return [
            ['name' => 'company', 'label' => __('Company'), 'options' => Company::orderBy('name')->pluck('name', 'id')->all(), 'value' => $request->query('company')],
            ['name' => 'account', 'label' => __('Received in'), 'options' => PlatformAccount::orderBy('name')->pluck('name', 'id')->all() + ['none' => __('Paid, no account chosen')], 'value' => $request->query('account')],
            ['name' => 'currency', 'label' => __('Currency'), 'options' => Subscription::query()->distinct()->orderBy('currency')->pluck('currency', 'currency')->all(), 'value' => strtoupper((string) $request->query('currency'))],
            ['name' => 'paid', 'label' => __('Paid or not'), 'options' => ['no' => __('Waiting'), 'yes' => __('Paid'), 'rejected' => __('Rejected')], 'value' => $request->query('paid')],
        ];
    }

    protected function totals(Request $request): array
    {
        $totals = $this->query($request)->whereNotNull('paid_on')->selectRaw('currency, sum(amount) as total')->groupBy('currency')->pluck('total', 'currency')->all();

        return $totals ? ['Received' => sums($totals)] : [];
    }

    protected function fields(?Model $record): array
    {
        $accounts = PlatformAccount::orderBy('name')->get()->mapWithKeys(fn (PlatformAccount $a) => [$a->id => $a->name.' ('.$a->currency.')'])->all();

        return [
            ['name' => 'company_id', 'label' => 'Company', 'type' => 'select', 'search' => true, 'options' => Company::orderBy('name')->pluck('name', 'id')->all(), 'default' => request()->query('company'), 'rules' => ['required', 'exists:companies,id']],
            ['name' => 'plan_id', 'label' => 'Plan', 'type' => 'select', 'options' => Plan::orderBy('sort')->get()->mapWithKeys(fn (Plan $p) => [$p->id => $p->label()])->all(), 'rules' => ['required', 'exists:plans,id'], 'quick' => ['New plan', 'admin.plans.create']],
            ['name' => 'starts_on', 'label' => 'From', 'type' => 'date', 'default' => now()->toDateString(), 'rules' => ['required', 'date']],
            ['name' => 'ends_on', 'label' => 'Until', 'type' => 'date', 'default' => now()->addYear()->toDateString(), 'rules' => ['nullable', 'date', 'after_or_equal:starts_on'], 'help' => 'Leave empty for a lifetime plan.'],
            ['name' => 'amount', 'label' => 'Amount', 'type' => 'decimal', 'rules' => ['required', 'numeric', 'min:0']],
            ['name' => 'currency', 'label' => 'Currency', 'default' => 'USD', 'rules' => ['required', 'alpha', 'size:3']],
            // "Confirm" on a request of a company opens this form with today filled in: check it and save
            ['name' => 'paid_on', 'label' => 'Paid on', 'type' => 'date', 'value' => $record && ! $record->paid_on && request()->boolean('confirm') ? ($record->sent_on ?? now()->toDateString()) : $record?->paid_on,
                'rules' => ['nullable', 'date'], 'help' => 'Leave empty while the money has not come. Filling it in confirms the payment and gives the company access until the end date.'],
            ['name' => 'platform_account_id', 'label' => 'Received in', 'type' => 'select', 'options' => $accounts, 'rules' => ['nullable', 'required_with:paid_on', 'exists:platform_accounts,id'],
                'help' => 'Needed once the payment is confirmed, so the statement of the account shows where the money came from.', 'quick' => ['New financial account', 'admin.accounts.create']],
            ['name' => 'reference', 'label' => 'Transfer or receipt number', 'rules' => ['nullable', 'string', 'max:255']],
            ['name' => 'note', 'label' => 'Note', 'rules' => ['nullable', 'string', 'max:255'], 'col' => 12],
        ];
    }

    protected function columns(): array
    {
        return [
            'Company' => fn (Subscription $s) => $s->company?->name,
            'Plan' => fn (Subscription $s) => $s->plan?->name,
            'Period' => fn (Subscription $s) => fmt_date($s->starts_on).' – '.($s->ends_on ? fmt_date($s->ends_on) : __('no end')),
            'Amount' => fn (Subscription $s) => money($s->amount, $s->currency),
            'Paid on' => fn (Subscription $s) => match (true) {
                $s->isPaid() => fmt_date($s->paid_on),
                $s->isRejected() => new HtmlString('<span class="badge bg-danger-subtle text-danger-emphasis">'.e(__('Rejected')).'</span> <span class="small text-muted">'.e($s->rejectLabel()).'</span>'),
                $s->requested => new HtmlString('<span class="badge bg-warning-subtle text-warning-emphasis">'.e(__('Waiting for you')).'</span>'),
                default => __('Not paid yet'),
            },
            'Received in' => fn (Subscription $s) => $s->account?->name,
        ];
    }

    protected function canEdit(Model $record): bool
    {
        return ! OfficeEntry::where('subscription_id', $record->id)->whereIn('status', ['pending', 'approved'])->exists();
    }

    protected function beforeSave(array $data, ?Model $record, Request $request): array
    {
        OfficeController::lockRegister();
        abort_if($record && ! $this->canEdit($record), 409);
        if (! empty($data['paid_on']) && OfficeEntry::where('company_id', $data['company_id'])
            ->where('kind', 'subscription')->where('status', 'pending')->exists()) {
            throw new UserError(__('Review the pending collection in Office approvals first.'));
        }
        $data['currency'] = strtoupper($data['currency']);

        return $data;
    }

    /** Once the money is confirmed (Paid on is filled in), the company follows the subscription. */
    protected function afterSave(Model $record, array $virtual, Request $request, bool $created): void
    {
        // a payment that is confirmed after all is no longer a rejected one
        if ($record->isPaid() && $record->rejected_at) {
            $record->update(['rejected_at' => null, 'reject_reason' => null, 'reject_note' => null]);
        }
        $newlyPaid = $record->isPaid() && ! $record->receipt_no;
        $record->assignReceiptNumber();
        $record->apply();

        if ($newlyPaid) {
            AdminLog::record('confirmed payment', $record->company?->name, money($record->amount, $record->currency).' · '.$record->receiptNumber());
            Lifecycle::paymentConfirmed($record);
        }
    }

    protected function auditLabel(Model $record): string
    {
        return ($record->company?->name ?? '#'.$record->getKey()).' · '.money($record->amount, $record->currency);
    }

    protected function rowLinks(Model $record): array
    {
        $links = [];
        if ($record->receipt_path) {
            $links[] = ['label' => __('Screenshot'), 'icon' => 'paperclip', 'url' => route('admin.subscriptions.receipt', ['id' => $record->id]), 'blank' => true];
        }
        if ($record->receipt_no) {
            $links[] = ['label' => $record->receiptNumber(), 'icon' => 'print', 'url' => route('admin.subscriptions.print', ['id' => $record->id]), 'blank' => true];
        }
        if (auth('admin')->user()->isSuper() && $this->canEdit($record) && ! $record->isPaid() && ! $record->isRejected()) {
            $links[] = ['label' => __('Confirm'), 'icon' => 'check', 'class' => 'btn-soft-success', 'url' => route('admin.subscriptions.edit', ['id' => $record->id, 'confirm' => 1])];
        }
        if (auth('admin')->user()->isSuper() && $this->canEdit($record) && $record->isWaiting()) {
            $links[] = ['label' => __('Reject'), 'icon' => 'ban', 'class' => 'btn-soft-danger', 'url' => route('admin.subscriptions.reject', ['id' => $record->id])];
        }

        return $links;
    }

    /** The form to reject an "I have paid" request: the reason is picked from a list. */
    public function reject(int $id): View
    {
        $subscription = Subscription::with('company')->findOrFail($id);
        abort_unless($subscription->isWaiting() && $this->canEdit($subscription), 404);

        return view('admin.reject', ['subscription' => $subscription, 'reasons' => config('tenders.reject_reasons')]);
    }

    /** Reject it: the company is told why by email and under its bell, and can send a new screenshot. */
    public function storeReject(Request $request, int $id): RedirectResponse
    {
        $subscription = Subscription::with('company')->findOrFail($id);
        abort_unless($subscription->isWaiting(), 404);

        $data = $request->validate([
            'reject_reason' => ['required', Rule::in(array_keys(config('tenders.reject_reasons')))],
            'reject_note' => ['nullable', 'string', 'max:255'],
        ], ['reject_reason.required' => __('Choose the reason.')]);

        DB::transaction(function () use ($subscription, $data) {
            OfficeController::lockRegister();
            $subscription->refresh();
            abort_unless($subscription->isWaiting() && $this->canEdit($subscription), 409);
            $subscription->update($data + ['rejected_at' => now()]);
        });
        AdminLog::record('rejected payment', $subscription->company?->name, money($subscription->amount, $subscription->currency).' · '.$subscription->rejectLabel());

        $company = $subscription->company;
        $users = User::where('company_id', $company->id)->where('is_active', true)->get();
        foreach ($users as $user) {
            Notice::create([
                'company_id' => $company->id,
                'user_id' => $user->id,
                'title' => __('Your payment was not accepted'),
                'body' => $subscription->rejectText(),
                'url' => route('billing.show'),
            ]);
        }

        $to = $users->where('role', User::OWNER)->pluck('email')->merge([$company->email])->filter()->unique()->values()->all();
        if ($to) {
            try {
                Mail::to($to)->send(new PaymentRejected($subscription));
            } catch (Throwable $e) {
                report($e);
            }
        }

        return redirect()->route('admin.subscriptions.index', ['paid' => 'no'])->with('success', __('The payment of :name is rejected and the company is told why.', ['name' => $company->name]));
    }

    /** The numbered receipt of a confirmed payment, ready to print or save as PDF. */
    public function print(int $id): View
    {
        $subscription = Subscription::with('company', 'plan', 'account')->whereNotNull('receipt_no')->findOrFail($id);

        return view('receipts.show', ['subscription' => $subscription]);
    }

    /** The receipt a company uploaded with its "I have paid" request. */
    public function receipt(int $id): StreamedResponse
    {
        $subscription = Subscription::findOrFail($id);
        abort_unless($subscription->receipt_path && Storage::disk('local')->exists($subscription->receipt_path), 404);

        return Storage::disk('local')->response($subscription->receipt_path);
    }
}
