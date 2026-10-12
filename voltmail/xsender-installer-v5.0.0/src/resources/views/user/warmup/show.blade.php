@extends('user.layouts.app')
@section('panel')

<main class="main-body">
    <div class="container-fluid">
        <div class="page-header d-flex justify-content-between align-items-center mb-4">
            <div class="page-header-left">
                <h3 class="mb-1">{{ $schedule->gateway?->name ?? translate('Mailbox Warmup') }}</h3>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="{{ route('user.dashboard') }}">{{ translate("Dashboard") }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('user.warmup.index') }}">{{ translate("Email Warmup") }}</a></li>
                        <li class="breadcrumb-item active">{{ translate("Details") }}</li>
                    </ol>
                </nav>
            </div>
            <div class="page-header-right d-flex gap-2">
                <a href="{{ route('user.warmup.edit', $schedule->id) }}" class="i-btn btn--primary outline btn--md">
                    <i class="ri-edit-line me-1"></i> {{ translate("Edit Settings") }}
                </a>
                <a href="{{ route('user.warmup.index') }}" class="i-btn btn--dark outline btn--md">
                    <i class="ri-arrow-left-line me-1"></i> {{ translate("Back to List") }}
                </a>
            </div>
        </div>

        {{-- Overview Cards --}}
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card p-3">
                    <span class="text-muted fs-13 d-block mb-1">{{ translate("Status") }}</span>
                    <span class="i-badge {{ $schedule->status === 'active' ? 'success-soft' : 'warning-soft' }} pill">
                        {{ ucfirst($schedule->status) }}
                    </span>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card p-3">
                    <span class="text-muted fs-13 d-block mb-1">{{ translate("Current Day / Quota") }}</span>
                    <h5 class="mb-0 fw-bold">{{ translate("Day") }} {{ $schedule->current_day }} ({{ $schedule->current_daily_quota }} {{ translate("target") }})</h5>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card p-3">
                    <span class="text-muted fs-13 d-block mb-1">{{ translate("Target Daily Limit") }}</span>
                    <h5 class="mb-0 fw-bold">{{ $schedule->daily_limit }} {{ translate("max/day") }}</h5>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card p-3">
                    <span class="text-muted fs-13 d-block mb-1">{{ translate("Ramp-up / Reply Rate") }}</span>
                    <h5 class="mb-0 fw-bold">+{{ $schedule->rampup_increment }}/day ({{ $schedule->reply_rate_percent }}%)</h5>
                </div>
            </div>
        </div>

        {{-- Dispatches Outbox Table --}}
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title">{{ translate("Recent Warmup Dispatches") }}</h4>
                <span class="badge bg-light text-dark">{{ $dispatches->total() }} {{ translate("Records") }}</span>
            </div>
            <div class="card-body px-0 pt-0">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th scope="col">{{ translate("Recipient") }}</th>
                                <th scope="col">{{ translate("Subject") }}</th>
                                <th scope="col">{{ translate("Slot / Date") }}</th>
                                <th scope="col">{{ translate("Status") }}</th>
                                <th scope="col">{{ translate("Placement") }}</th>
                                <th scope="col">{{ translate("Sent Time") }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($dispatches as $dispatch)
                            <tr>
                                <td>
                                    <span class="fw-medium">{{ $dispatch->recipient_email }}</span>
                                </td>
                                <td>
                                    <span class="text-truncate d-inline-block" style="max-width: 250px;">{{ $dispatch->subject }}</span>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark">Slot #{{ $dispatch->slot_number }}</span>
                                    <small class="text-muted d-block">{{ $dispatch->dispatch_date?->format('Y-m-d') }}</small>
                                </td>
                                <td>
                                    @php
                                        $badgeClass = match($dispatch->status) {
                                            'delivered' => 'success-soft',
                                            'sent'      => 'info-soft',
                                            'failed'    => 'danger-soft',
                                            default     => 'warning-soft',
                                        };
                                    @endphp
                                    <span class="i-badge {{ $badgeClass }} pill">
                                        {{ ucfirst($dispatch->status) }}
                                    </span>
                                </td>
                                <td>
                                    @if($dispatch->inbox_placement === 'inbox')
                                        <span class="badge bg-success text-white"><i class="ri-inbox-line me-1"></i>Inbox</span>
                                    @elseif($dispatch->inbox_placement === 'spam')
                                        <span class="badge bg-danger text-white"><i class="ri-spam-line me-1"></i>Spam</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    {{ $dispatch->sent_at ? $dispatch->sent_at->diffForHumans() : translate('Pending') }}
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">
                                    {{ translate("No warmup dispatches generated yet. The scheduler will generate slots based on your daily quota.") }}
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($dispatches->hasPages())
                <div class="card-footer py-3">
                    {{ $dispatches->links() }}
                </div>
            @endif
        </div>
    </div>
</main>

@endsection
