@extends('user.layouts.app')
@section('panel')

<main class="main-body">
    <div class="container-fluid">
        <div class="page-header d-flex justify-content-between align-items-center mb-4">
            <div class="page-header-left">
                <h3 class="mb-1">{{ translate("Email Warmup Engine") }}</h3>
                <p class="text-muted mb-0">{{ translate("Safely build sender reputation for your configured mailboxes using automated peer-to-peer schedules.") }}</p>
            </div>
            <div class="page-header-right">
                <a href="{{ route('user.warmup.create') }}" class="i-btn btn--primary btn--md">
                    <i class="ri-add-line me-1"></i> {{ translate("Add Mailbox to Warmup") }}
                </a>
            </div>
        </div>

        {{-- Metrics Overview --}}
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card p-3 d-flex flex-row align-items-center justify-content-between">
                    <div>
                        <span class="text-muted d-block mb-1">{{ translate("Active Warmup Mailboxes") }}</span>
                        <h4 class="mb-0 fw-bold">{{ $stats['total_active'] ?? 0 }}</h4>
                    </div>
                    <div class="rounded-circle p-3 bg-primary-subtle text-primary">
                        <i class="ri-mail-send-line fs-3"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card p-3 d-flex flex-row align-items-center justify-content-between">
                    <div>
                        <span class="text-muted d-block mb-1">{{ translate("Total Dispatches") }}</span>
                        <h4 class="mb-0 fw-bold">{{ $stats['total_dispatches'] ?? 0 }}</h4>
                    </div>
                    <div class="rounded-circle p-3 bg-info-subtle text-info">
                        <i class="ri-send-plane-line fs-3"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card p-3 d-flex flex-row align-items-center justify-content-between">
                    <div>
                        <span class="text-muted d-block mb-1">{{ translate("Delivered Messages") }}</span>
                        <h4 class="mb-0 fw-bold">{{ $stats['total_delivered'] ?? 0 }}</h4>
                    </div>
                    <div class="rounded-circle p-3 bg-success-subtle text-success">
                        <i class="ri-checkbox-circle-line fs-3"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title">{{ translate("Warmup Mailbox Schedules") }}</h4>
                <span class="badge bg-light text-dark">{{ $schedules->total() }} {{ translate("Configured") }}</span>
            </div>
            <div class="card-body px-0 pt-0">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th scope="col">{{ translate("Mailbox / Sender") }}</th>
                                <th scope="col">{{ translate("Provider") }}</th>
                                <th scope="col">{{ translate("Daily Quota") }}</th>
                                <th scope="col">{{ translate("Ramp-up") }}</th>
                                <th scope="col">{{ translate("Reply Rate") }}</th>
                                <th scope="col">{{ translate("Status") }}</th>
                                <th scope="col" class="text-end">{{ translate("Action") }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($schedules as $schedule)
                            @php
                                $gateway = $schedule->gateway;
                                $hasImap = !empty($gateway->meta_data['imap']['host']);
                            @endphp
                            <tr>
                                <td>
                                    <div class="d-flex flex-column">
                                        <a href="{{ route('user.warmup.show', $schedule->id) }}" class="fw-semibold text-dark">
                                            {{ $gateway?->name ?? translate('Mailbox #') . $schedule->gateway_id }}
                                        </a>
                                        <small class="text-muted">{{ $gateway?->address ?? translate('N/A') }}</small>
                                        @if($hasImap)
                                            <span class="badge bg-success-subtle text-success mt-1" style="font-size: 10px; width: fit-content;">
                                                <i class="ri-check-line me-1"></i>{{ translate("IMAP Verified") }}
                                            </span>
                                        @else
                                            <span class="badge bg-warning-subtle text-warning mt-1" style="font-size: 10px; width: fit-content;">
                                                <i class="ri-alert-line me-1"></i>{{ translate("IMAP Pending") }}
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark text-uppercase">
                                        {{ $gateway?->type ?? 'SMTP' }}
                                    </span>
                                </td>
                                <td>
                                    <span class="fw-bold">{{ $schedule->current_daily_quota }}</span> / {{ $schedule->daily_limit }}
                                    <small class="text-muted d-block">{{ translate("emails/day") }}</small>
                                </td>
                                <td>
                                    <span class="text-success fw-medium">+{{ $schedule->rampup_increment }}</span>
                                    <small class="text-muted d-block">{{ translate("per day") }}</small>
                                </td>
                                <td>
                                    <span class="fw-medium">{{ $schedule->reply_rate_percent }}%</span>
                                </td>
                                <td>
                                    <span class="i-badge {{ $schedule->status === 'active' ? 'success-soft' : 'warning-soft' }} pill">
                                        {{ ucfirst($schedule->status) }}
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center justify-content-end gap-2">
                                        <a href="{{ route('user.warmup.show', $schedule->id) }}" class="icon-btn btn-ghost btn-sm" title="{{ translate('View Activity') }}">
                                            <i class="ri-eye-line"></i>
                                        </a>
                                        <a href="{{ route('user.warmup.edit', $schedule->id) }}" class="icon-btn btn-ghost btn-sm" title="{{ translate('Edit Settings') }}">
                                            <i class="ri-edit-line"></i>
                                        </a>
                                        <form action="{{ route('user.warmup.toggle-status', $schedule->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="icon-btn btn-ghost btn-sm" title="{{ $schedule->status === 'active' ? translate('Pause Warmup') : translate('Resume Warmup') }}">
                                                <i class="{{ $schedule->status === 'active' ? 'ri-pause-circle-line text-warning' : 'ri-play-circle-line text-success' }}"></i>
                                            </button>
                                        </form>
                                        <form action="{{ route('user.warmup.destroy', $schedule->id) }}" method="POST" class="d-inline" onsubmit="return confirm('{{ translate('Are you sure you want to remove this mailbox from warmup?') }}')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="icon-btn btn-ghost btn-sm text-danger" title="{{ translate('Delete') }}">
                                                <i class="ri-delete-bin-line"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <div class="empty-state">
                                        <i class="ri-mail-volume-line fs-1 text-muted mb-3 d-block"></i>
                                        <h5>{{ translate("No Warmup Mailboxes Configured") }}</h5>
                                        <p class="text-muted">{{ translate("Select any of your existing configured email accounts to start warming up.") }}</p>
                                        <a href="{{ route('user.warmup.create') }}" class="i-btn btn--primary btn--md mt-2">
                                            <i class="ri-add-line me-1"></i> {{ translate("Configure First Mailbox") }}
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($schedules->hasPages())
                <div class="card-footer py-3">
                    {{ $schedules->links() }}
                </div>
            @endif
        </div>
    </div>
</main>

@endsection
