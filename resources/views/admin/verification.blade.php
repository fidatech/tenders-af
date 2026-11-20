@extends('layouts.admin', ['title' => __('Verification')])

@php
    $tabs = ['pending' => 'Waiting', 'verified' => 'Verified', 'rejected' => 'Refused'];
@endphp

@section('content')
<x-page-header :title="__('Verification')" icon="shield-halved" :subtitle="trans_choice('{0} No company is waiting|{1} 1 company is waiting|[2,*] :count companies are waiting', $waiting, ['count' => $waiting])" />

<div class="card">
    <div class="card-body">
        <div class="btn-group mb-3" role="group" aria-label="{{ __('Status') }}">
            @foreach ($tabs as $key => $label)
                <a class="btn btn-sm {{ $status === $key ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ route('admin.verification.index', ['status' => $key]) }}">{{ __($label) }}</a>
            @endforeach
        </div>

        @if ($rows->isEmpty())
            <div class="empty-state">
                <div class="es-icon"><x-icon name="shield-halved" /></div>
                <h3>{{ __('Nothing here') }}</h3>
            </div>
        @else
            <table class="table table-hover w-100 align-middle">
                <thead><tr><th>{{ __('Company') }}</th><th>{{ __('Licence number') }}</th><th>{{ __('Province') }}</th><th>{{ $status === 'pending' ? __('Sent on') : ($status === 'verified' ? __('Verified on') : __('Reason')) }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
                <tbody>
                    @foreach ($rows as $company)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <span class="vr-avatar">{{ \App\Models\User::initialsOf($company->name) }}</span>
                                    <span class="min-w-0"><a class="fw-semibold text-decoration-none d-block text-truncate" href="{{ route('admin.companies.edit', ['id' => $company->id]) }}">{{ $company->name }}</a><span class="d-block small text-muted text-truncate">{{ $company->kindLabel() }} · {{ $company->email }}</span></span>
                                </div>
                            </td>
                            <td dir="ltr"><span class="fw-semibold">{{ $company->license_no ?: '—' }}</span>@if ($company->tin)<span class="d-block small text-muted">TIN {{ $company->tin }}</span>@endif</td>
                            <td>{{ $company->province?->name ?: ($company->country ?: '—') }}</td>
                            <td class="small">
                                @if ($status === 'pending')
                                    <span class="text-nowrap">{{ fmt_date($company->verify_requested_at) }}</span><span class="d-block text-muted">{{ $company->verify_requested_at?->diffForHumans() }}</span>
                                @elseif ($status === 'verified')
                                    <span class="text-nowrap">{{ fmt_date($company->verified_at) }}</span>
                                @else
                                    <span class="text-danger">{{ $company->verify_note }}</span>
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                @if ($status === 'pending')
                                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#review-{{ $company->id }}"><x-icon name="magnifying-glass" class="me-1" />{{ __('Review') }}</button>
                                @elseif ($company->verify_doc_path)
                                    <a class="btn btn-sm btn-light-secondary" href="{{ route('admin.verification.document', ['id' => $company->id]) }}" target="_blank" rel="noopener"><x-icon name="file-lines" class="me-1" />{{ __('Licence') }}</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            {{ $rows->links('partials.pagination') }}
        @endif
    </div>
</div>

{{-- one review window per company that waits: the licence beside its details, then the decision --}}
@if ($status === 'pending')
    @foreach ($rows as $company)
        @php
            $doc = $company->verify_doc_path;
            $isPdf = $doc && str_ends_with(strtolower($doc), '.pdf');
            $docUrl = $doc ? route('admin.verification.document', ['id' => $company->id]) : null;
            $facts = array_filter([
                __('Name on the site') => $company->name,
                __('Registered as') => $company->kindLabel(),
                __('Licence number') => $company->license_no,
                __('Tax number (TIN)') => $company->tin,
                __('Province') => $company->province?->name ?: $company->country,
                __('Email') => $company->email,
                __('Phone') => $company->phone,
                __('Registered on') => fmt_date($company->created_at),
                __('Sent on') => fmt_datetime($company->verify_requested_at),
            ]);
        @endphp
        <div class="modal fade" id="review-{{ $company->id }}" tabindex="-1" aria-labelledby="review-title-{{ $company->id }}" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <div class="min-w-0">
                            <h5 class="modal-title" id="review-title-{{ $company->id }}">{{ __('Review :name', ['name' => $company->name]) }}</h5>
                            <div class="small text-muted">{{ __('Compare the licence with the details the company gave, then decide.') }}</div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-4">
                            <div class="col-12 col-lg-7">
                                <div class="vr-doc">
                                    @if (! $doc)
                                        <div class="vr-doc-empty"><x-icon name="file-circle-xmark" /><span>{{ __('No document was sent.') }}</span></div>
                                    @elseif ($isPdf)
                                        <iframe src="{{ $docUrl }}#view=FitH" title="{{ __('Licence of :name', ['name' => $company->name]) }}" loading="lazy"></iframe>
                                    @else
                                        <img src="{{ $docUrl }}" alt="{{ __('Licence of :name', ['name' => $company->name]) }}" loading="lazy">
                                    @endif
                                </div>
                                @if ($doc)
                                    <a class="small fw-semibold text-decoration-none d-inline-flex align-items-center gap-1 mt-2" href="{{ $docUrl }}" target="_blank" rel="noopener"><x-icon name="up-right-from-square" /> {{ __('Open the licence in a new tab') }}</a>
                                @endif
                            </div>
                            <div class="col-12 col-lg-5">
                                <dl class="vr-facts">
                                    @foreach ($facts as $label => $value)
                                        <div><dt>{{ $label }}</dt><dd dir="auto">{{ $value }}</dd></div>
                                    @endforeach
                                </dl>
                                <div class="vr-check">
                                    <div class="fw-semibold mb-1">{{ __('Before you verify, check that') }}</div>
                                    <ul class="mb-0">
                                        <li>{{ __('the name on the licence is the name on the site') }}</li>
                                        <li>{{ __('the licence number matches') }}</li>
                                        <li>{{ __('the licence has not expired') }}</li>
                                        <li>{{ __('every page can be read') }}</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <form method="POST" action="{{ route('admin.verification.approve', ['id' => $company->id]) }}" class="vr-decide" data-approve="{{ route('admin.verification.approve', ['id' => $company->id]) }}" data-reject="{{ route('admin.verification.reject', ['id' => $company->id]) }}">
                            @csrf
                            <div class="fw-semibold mb-2">{{ __('Your decision') }}</div>
                            <div class="vr-choices">
                                <label class="vr-choice ok"><input type="radio" name="decision" value="approve" checked><span><x-icon name="circle-check" /><b>{{ __('Verify') }}</b><small>{{ __('The company gets the Verified badge.') }}</small></span></label>
                                <label class="vr-choice no"><input type="radio" name="decision" value="reject"><span><x-icon name="circle-xmark" /><b>{{ __('Refuse') }}</b><small>{{ __('The company is told why and can send again.') }}</small></span></label>
                            </div>
                            <fieldset class="vr-reasons" hidden disabled>
                                <legend class="fw-semibold mb-2 fs-6">{{ __('Why? The company reads this.') }}</legend>
                                @foreach ($reasons as $key => $text)
                                    <label class="vr-reason"><input type="radio" name="reason" value="{{ $key }}" required><span>{{ __($text) }}</span></label>
                                @endforeach
                                <label class="vr-reason"><input type="radio" name="reason" value="other" required><span>{{ __('Another reason, written below') }}</span></label>
                                <label class="form-label mt-2" for="vr-note-{{ $company->id }}">{{ __('Note to the company') }} <span class="text-muted fw-normal">({{ __('optional, needed for another reason') }})</span></label>
                                <textarea class="form-control" id="vr-note-{{ $company->id }}" name="note" rows="2" maxlength="300" dir="auto" placeholder="{{ __('For example: the licence on page 2 is cut off.') }}"></textarea>
                            </fieldset>
                            <div class="d-flex justify-content-end gap-2 mt-3">
                                <button type="button" class="btn btn-light-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                                <button type="submit" class="btn btn-success" data-label-approve="{{ __('Verify the company') }}" data-label-reject="{{ __('Refuse and tell the company') }}"><x-icon name="check" class="me-1" /><span>{{ __('Verify the company') }}</span></button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
@endif

@push('scripts')
<script>
// the decision switches the form between "verify" and "refuse", and shows the reasons only when refusing
document.querySelectorAll('.vr-decide').forEach(function (form) {
    var reasons = form.querySelector('.vr-reasons'), button = form.querySelector('button[type=submit]'), note = form.querySelector('textarea');
    var sync = function () {
        var refuse = form.querySelector('input[name=decision]:checked').value === 'reject';
        form.action = form.getAttribute(refuse ? 'data-reject' : 'data-approve');
        reasons.hidden = reasons.disabled = !refuse;
        button.className = 'btn ' + (refuse ? 'btn-danger' : 'btn-success');
        button.querySelector('span').textContent = button.getAttribute(refuse ? 'data-label-reject' : 'data-label-approve');
        var other = form.querySelector('input[name=reason][value=other]:checked');
        note.required = refuse && !!other;
    };
    form.addEventListener('change', sync);
    sync();
});
</script>
@endpush
@endsection
