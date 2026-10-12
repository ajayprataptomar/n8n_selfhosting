@extends('user.layouts.app')
@section('panel')

<main class="main-body">
    <div class="container-fluid">
        <div class="page-header d-flex justify-content-between align-items-center mb-4">
            <div class="page-header-left">
                <h3 class="mb-1">{{ translate("Edit Warmup Settings") }}</h3>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="{{ route('user.dashboard') }}">{{ translate("Dashboard") }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('user.warmup.index') }}">{{ translate("Email Warmup") }}</a></li>
                        <li class="breadcrumb-item active">{{ translate("Edit Settings") }}</li>
                    </ol>
                </nav>
            </div>
            <div class="page-header-right">
                <a href="{{ route('user.warmup.index') }}" class="i-btn btn--dark outline btn--md">
                    <i class="ri-arrow-left-line me-1"></i> {{ translate("Back to List") }}
                </a>
            </div>
        </div>

        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header">
                        <h4 class="card-title">{{ translate("Associated Mailbox") }}</h4>
                    </div>
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between p-3 bg-light rounded-3">
                            <div>
                                <h5 class="mb-1 fw-bold">{{ $schedule->gateway?->name }}</h5>
                                <span class="text-muted">{{ $schedule->gateway?->address }} [{{ strtoupper($schedule->gateway?->type) }}]</span>
                            </div>
                            <span class="i-badge {{ $schedule->status === 'active' ? 'success-soft' : 'warning-soft' }} pill">
                                {{ ucfirst($schedule->status) }}
                            </span>
                        </div>
                    </div>
                </div>

                <form action="{{ route('user.warmup.update', $schedule->id) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title">{{ translate("Adjust Schedule Parameters") }}</h4>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label required">{{ translate("Maximum Daily Target Limit") }}</label>
                                    <input type="number" name="daily_limit" class="form-control" value="{{ old('daily_limit', $schedule->daily_limit) }}" min="5" max="500" required>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label required">{{ translate("Daily Ramp-up Increment") }}</label>
                                    <input type="number" name="rampup_increment" class="form-control" value="{{ old('rampup_increment', $schedule->rampup_increment) }}" min="1" max="50" required>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">{{ translate("Simulated Reply Rate (%)") }}</label>
                                    <input type="number" name="reply_rate_percent" class="form-control" value="{{ old('reply_rate_percent', $schedule->reply_rate_percent) }}" min="0" max="100">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">{{ translate("Status") }}</label>
                                    <select name="status" class="form-select">
                                        <option value="active" {{ $schedule->status === 'active' ? 'selected' : '' }}>{{ translate("Active") }}</option>
                                        <option value="paused" {{ $schedule->status === 'paused' ? 'selected' : '' }}>{{ translate("Paused") }}</option>
                                    </select>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end gap-2 mt-4">
                                <a href="{{ route('user.warmup.index') }}" class="i-btn btn--dark outline btn--md">{{ translate("Cancel") }}</a>
                                <button type="submit" class="i-btn btn--primary btn--md">{{ translate("Update Settings") }}</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

@endsection
