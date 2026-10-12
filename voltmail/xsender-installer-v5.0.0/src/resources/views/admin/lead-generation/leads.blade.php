@push("style-include")
<link rel="stylesheet" href="{{ asset('assets/theme/global/css/select2.min.css')}}">
@endpush

@extends('admin.layouts.app')

@push('style-push')
<style>
/* Page Header */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 1.5rem;
}
.page-header-right {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
    margin-bottom: 1.5rem;
}
@media (max-width: 1200px) {
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 576px) {
    .stats-grid { grid-template-columns: 1fr; }
}

.stat-card {
    background: var(--card-bg, #fff);
    border-radius: 14px;
    padding: 1.25rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    border: 1px solid var(--color-border-light);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}
.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 3px;
    height: 100%;
    opacity: 0.7;
}
.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
}
.stat-card.total::before { background: linear-gradient(180deg, #6366f1, #8b5cf6); }
.stat-card.email::before { background: linear-gradient(180deg, #3b82f6, #0ea5e9); }
.stat-card.phone::before { background: linear-gradient(180deg, #10b981, #14b8a6); }
.stat-card.imported::before { background: linear-gradient(180deg, #f59e0b, #f97316); }

.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
}
.stat-card.total .stat-icon { background: rgba(99, 102, 241, 0.12); color: #6366f1; }
.stat-card.email .stat-icon { background: rgba(59, 130, 246, 0.12); color: #3b82f6; }
.stat-card.phone .stat-icon { background: rgba(16, 185, 129, 0.12); color: #10b981; }
.stat-card.imported .stat-icon { background: rgba(245, 158, 11, 0.12); color: #f59e0b; }

.stat-content {
    flex: 1;
    min-width: 0;
}
.stat-value {
    font-size: 1.5rem;
    font-weight: 700;
    line-height: 1.2;
    color: var(--text-primary);
}
.stat-label {
    font-size: 0.8125rem;
    color: var(--text-muted);
    font-weight: 500;
    margin-top: 2px;
}

/* Filter Card */
.filter-card {
    background: var(--card-bg, #fff);
    border-radius: 14px;
    padding: 1.25rem;
    margin-bottom: 1.5rem;
    border: 1px solid var(--color-border-light);
}
.filter-card .form-label {
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--text-muted);
    margin-bottom: 0.5rem;
}
.filter-card .form-control,
.filter-card .form-select {
    border-radius: 10px;
    border: 1px solid var(--color-border);
    padding: 0.625rem 1rem;
    font-size: 0.875rem;
    transition: all 0.2s;
}
.filter-card .form-control:focus,
.filter-card .form-select:focus {
    border-color: var(--color-primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}
.filter-btn {
    height: 42px;
    border-radius: 10px;
    min-width: 100px;
}

/* Leads Table Card */
.leads-card {
    background: var(--card-bg, #fff);
    border-radius: 14px;
    border: 1px solid var(--color-border-light);
    overflow: hidden;
}
.leads-card-header {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid var(--color-border-light);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}
.leads-card-header h4 {
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.leads-card-header h4 i {
    color: var(--color-primary);
}

/* Table Styles */
.leads-table-wrapper {
    overflow-x: auto;
}
.leads-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1000px;
}
.leads-table thead {
    background: var(--site-bg, #f8fafc);
}
.leads-table thead th {
    padding: 0.875rem 1rem;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--text-muted);
    border-bottom: 1px solid var(--color-border-light);
    white-space: nowrap;
}
.leads-table tbody tr {
    border-bottom: 1px solid var(--color-border-light);
    transition: background 0.15s ease;
}
.leads-table tbody tr:last-child {
    border-bottom: none;
}
.leads-table tbody tr:hover {
    background: var(--site-bg, #f8fafc);
}
.leads-table tbody td {
    padding: 0.875rem 1rem;
    vertical-align: middle;
    font-size: 0.875rem;
}

/* Lead Info Cell */
.lead-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.lead-avatar {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: linear-gradient(135deg, var(--color-primary), #8b5cf6);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-weight: 600;
    font-size: 0.875rem;
    flex-shrink: 0;
}
.lead-details h6 {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 2px 0;
}
.lead-details small {
    font-size: 0.75rem;
    color: var(--text-muted);
}

/* Contact Info */
.contact-info {
    display: flex;
    align-items: center;
    gap: 0.375rem;
}
.contact-info i {
    font-size: 0.875rem;
}
.contact-info.has-email { color: #3b82f6; }
.contact-info.has-phone { color: #10b981; }
.contact-info .verified {
    color: #10b981;
    font-size: 0.75rem;
}

/* Quality Badge */
.quality-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}
.quality-badge.excellent { background: rgba(16, 185, 129, 0.12); color: #10b981; }
.quality-badge.good { background: rgba(59, 130, 246, 0.12); color: #3b82f6; }
.quality-badge.fair { background: rgba(245, 158, 11, 0.12); color: #f59e0b; }
.quality-badge.poor { background: rgba(239, 68, 68, 0.12); color: #ef4444; }

/* Status Badge */
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}
.status-badge.imported { background: rgba(16, 185, 129, 0.12); color: #10b981; }
.status-badge.pending { background: rgba(245, 158, 11, 0.12); color: #f59e0b; }

/* Source Badge */
.source-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.2s;
}
.source-badge.maps { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
.source-badge.website { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
.source-badge:hover { opacity: 0.8; }

/* Action Buttons */
.action-buttons {
    display: flex;
    align-items: center;
    gap: 6px;
}
.action-btn {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    border: 1px solid var(--color-border);
    background: var(--card-bg, #fff);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-muted);
    transition: all 0.2s ease;
    cursor: pointer;
}
.action-btn:hover {
    border-color: var(--color-primary);
    color: var(--color-primary);
    background: rgba(99, 102, 241, 0.05);
}
.action-btn.view:hover {
    border-color: #3b82f6;
    color: #3b82f6;
    background: rgba(59, 130, 246, 0.05);
}
.action-btn.delete:hover {
    border-color: #ef4444;
    color: #ef4444;
    background: rgba(239, 68, 68, 0.05);
}

/* Bulk Action Bar */
.bulk-action-bar {
    background: linear-gradient(135deg, var(--color-primary), #8b5cf6);
    border-radius: 12px;
    padding: 1rem 1.25rem;
    margin-bottom: 1rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    color: #fff;
}
.bulk-action-bar .selected-text {
    font-weight: 600;
    font-size: 0.9375rem;
}
.bulk-action-bar .bulk-buttons {
    display: flex;
    gap: 0.5rem;
}
.bulk-action-bar .bulk-btn {
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-size: 0.8125rem;
    font-weight: 600;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.375rem;
    transition: all 0.2s;
}
.bulk-action-bar .bulk-btn.import {
    background: rgba(255, 255, 255, 0.2);
    color: #fff;
}
.bulk-action-bar .bulk-btn.import:hover {
    background: rgba(255, 255, 255, 0.3);
}
.bulk-action-bar .bulk-btn.delete {
    background: #ef4444;
    color: #fff;
}
.bulk-action-bar .bulk-btn.delete:hover {
    background: #dc2626;
}

/* Empty State */
.empty-state {
    padding: 4rem 2rem;
    text-align: center;
}
.empty-state-icon {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.1));
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.5rem;
}
.empty-state-icon i {
    font-size: 2rem;
    color: var(--color-primary);
}
.empty-state h5 {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
}
.empty-state p {
    color: var(--text-muted);
    margin-bottom: 1.5rem;
    max-width: 400px;
    margin-left: auto;
    margin-right: auto;
}

/* Pagination */
.pagination-wrapper {
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--color-border-light);
}

/* Modal Improvements */
.modal-content {
    border-radius: 16px;
    border: none;
    overflow: hidden;
}
.modal-header {
    border-bottom: 1px solid var(--color-border-light);
    padding: 1.25rem 1.5rem;
}
.modal-title {
    font-weight: 600;
    font-size: 1.125rem;
}
.modal-body {
    padding: 1.5rem;
}
.modal-footer {
    border-top: 1px solid var(--color-border-light);
    padding: 1rem 1.5rem;
}

/* Lead Detail Modal */
.lead-detail-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
}
.lead-detail-item {
    padding: 0.875rem;
    background: var(--site-bg, #f8fafc);
    border-radius: 10px;
}
.lead-detail-item label {
    display: block;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--text-muted);
    margin-bottom: 0.375rem;
}
.lead-detail-item span {
    font-size: 0.9375rem;
    font-weight: 500;
    color: var(--text-primary);
}

/* Dark Mode */
[data-theme="dark"] .stat-card,
[data-theme="dark"] .filter-card,
[data-theme="dark"] .leads-card {
    background: var(--card-bg-dark, #1f2937);
    border-color: var(--border-color-dark, #374151);
}
[data-theme="dark"] .leads-table thead {
    background: var(--bg-dark-2, #111827);
}
[data-theme="dark"] .leads-table tbody tr:hover {
    background: var(--bg-dark-2, #111827);
}
[data-theme="dark"] .action-btn {
    background: var(--card-bg-dark, #1f2937);
    border-color: var(--border-color-dark, #374151);
}
[data-theme="dark"] .lead-detail-item {
    background: var(--bg-dark-2, #111827);
}

/* Delete Modal */
.delete-icon-wrapper {
    display: flex;
    justify-content: center;
}
.delete-icon {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    background: rgba(239, 68, 68, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
}
.delete-icon i {
    font-size: 1.75rem;
    color: #ef4444;
}
</style>
@endpush

@section('panel')
<main class="main-body">
    <div class="container-fluid px-0 main-content">
        {{-- Page Header --}}
        <div class="page-header">
            <div class="page-header-left">
                <h2>{{ $title }}</h2>
                <div class="breadcrumb-wrapper">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            @foreach($breadcrumbs as $breadcrumb)
                                @if(isset($breadcrumb['url']))
                                    <li class="breadcrumb-item">
                                        <a href="{{ $breadcrumb['url'] }}">{{ $breadcrumb['name'] }}</a>
                                    </li>
                                @else
                                    <li class="breadcrumb-item active" aria-current="page">{{ $breadcrumb['name'] }}</li>
                                @endif
                            @endforeach
                        </ol>
                    </nav>
                </div>
            </div>
            <div class="page-header-right">
                <a href="{{ route('admin.lead-generation.index') }}" class="i-btn btn--primary btn--md">
                    <i class="ri-add-line"></i> {{ translate('New Scraping Job') }}
                </a>
                <a href="{{ route('admin.lead-generation.index') }}" class="i-btn btn--dark outline btn--md">
                    <i class="ri-arrow-left-line"></i> {{ translate('Back') }}
                </a>
            </div>
        </div>

        {{-- Stats Overview --}}
        <div class="stats-grid">
            <div class="stat-card total">
                <div class="stat-icon">
                    <i class="ri-team-line"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value">{{ number_format($leads->total()) }}</div>
                    <div class="stat-label">{{ translate('Total Leads') }}</div>
                </div>
            </div>
            <div class="stat-card email">
                <div class="stat-icon">
                    <i class="ri-mail-line"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value">{{ number_format($leads->where('email', '!=', null)->count()) }}</div>
                    <div class="stat-label">{{ translate('With Email') }}</div>
                </div>
            </div>
            <div class="stat-card phone">
                <div class="stat-icon">
                    <i class="ri-phone-line"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value">{{ number_format($leads->where('phone', '!=', null)->count()) }}</div>
                    <div class="stat-label">{{ translate('With Phone') }}</div>
                </div>
            </div>
            <div class="stat-card imported">
                <div class="stat-icon">
                    <i class="ri-checkbox-circle-line"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value">{{ number_format($leads->where('imported_at', '!=', null)->count()) }}</div>
                    <div class="stat-label">{{ translate('Imported') }}</div>
                </div>
            </div>
        </div>

        {{-- Filters --}}
        <div class="filter-card">
            <form action="{{ route('admin.lead-generation.leads') }}" method="GET">
                <div class="row g-3 align-items-end">
                    <div class="col-lg-3 col-md-6">
                        <label class="form-label">{{ translate('Search') }}</label>
                        <input type="text" name="search" class="form-control" value="{{ request('search') }}" placeholder="{{ translate('Business name, email...') }}">
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <label class="form-label">{{ translate('Has Email') }}</label>
                        <select class="form-select" name="has_email">
                            <option value="">{{ translate('Any') }}</option>
                            <option value="1" {{ request('has_email') == '1' ? 'selected' : '' }}>{{ translate('Yes') }}</option>
                            <option value="0" {{ request('has_email') == '0' ? 'selected' : '' }}>{{ translate('No') }}</option>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <label class="form-label">{{ translate('Has Phone') }}</label>
                        <select class="form-select" name="has_phone">
                            <option value="">{{ translate('Any') }}</option>
                            <option value="1" {{ request('has_phone') == '1' ? 'selected' : '' }}>{{ translate('Yes') }}</option>
                            <option value="0" {{ request('has_phone') == '0' ? 'selected' : '' }}>{{ translate('No') }}</option>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <label class="form-label">{{ translate('Quality Score') }}</label>
                        <select class="form-select" name="min_quality">
                            <option value="">{{ translate('Any') }}</option>
                            <option value="80" {{ request('min_quality') == '80' ? 'selected' : '' }}>80%+ {{ translate('Excellent') }}</option>
                            <option value="60" {{ request('min_quality') == '60' ? 'selected' : '' }}>60%+ {{ translate('Good') }}</option>
                            <option value="40" {{ request('min_quality') == '40' ? 'selected' : '' }}>40%+ {{ translate('Fair') }}</option>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <label class="form-label">{{ translate('Status') }}</label>
                        <select class="form-select" name="not_imported">
                            <option value="">{{ translate('All') }}</option>
                            <option value="1" {{ request('not_imported') == '1' ? 'selected' : '' }}>{{ translate('Not Imported') }}</option>
                            <option value="0" {{ request('not_imported') == '0' ? 'selected' : '' }}>{{ translate('Imported') }}</option>
                        </select>
                    </div>
                    <div class="col-lg-1 col-md-6">
                        <div class="d-flex gap-2">
                            <button type="submit" class="i-btn btn--primary filter-btn w-100">
                                <i class="ri-search-line"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        {{-- Bulk Action Bar --}}
        <div class="bulk-action-bar d-none" id="bulkActionBar">
            <div class="selected-text">
                <i class="ri-checkbox-multiple-line me-1"></i>
                <span id="selectedCount">0</span> {{ translate('leads selected') }}
            </div>
            <div class="bulk-buttons">
                <button class="bulk-btn import" data-bs-toggle="modal" data-bs-target="#importModal">
                    <i class="ri-download-2-line"></i> {{ translate('Import to Contacts') }}
                </button>
                <button class="bulk-btn delete" id="bulkDeleteBtn">
                    <i class="ri-delete-bin-line"></i> {{ translate('Delete') }}
                </button>
            </div>
        </div>

        {{-- Leads Table --}}
        <div class="leads-card">
            <div class="leads-card-header">
                <h4><i class="ri-contacts-book-line"></i> {{ translate('All Scraped Leads') }}</h4>
                <span class="text-muted">{{ $leads->total() }} {{ translate('total leads') }}</span>
            </div>

            @if($leads->count() > 0)
            <div class="leads-table-wrapper">
                <table class="leads-table">
                    <thead>
                        <tr>
                            <th style="width: 40px;">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="selectAll">
                                </div>
                            </th>
                            <th>{{ translate('Business / Name') }}</th>
                            <th>{{ translate('Email') }}</th>
                            <th>{{ translate('Phone') }}</th>
                            <th>{{ translate('Location') }}</th>
                            <th>{{ translate('Quality') }}</th>
                            <th>{{ translate('Status') }}</th>
                            <th>{{ translate('Source') }}</th>
                            <th class="text-end">{{ translate('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($leads as $lead)
                        <tr>
                            <td>
                                <div class="form-check">
                                    <input class="form-check-input lead-checkbox" type="checkbox" value="{{ $lead->id }}" data-id="{{ $lead->id }}">
                                </div>
                            </td>
                            <td>
                                <div class="lead-info">
                                    <div class="lead-avatar">
                                        {{ strtoupper(substr($lead->display_name ?? 'L', 0, 2)) }}
                                    </div>
                                    <div class="lead-details">
                                        <h6>{{ Str::limit($lead->display_name, 25) }}</h6>
                                        @if($lead->category)
                                        <small>{{ Str::limit($lead->category, 30) }}</small>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td>
                                @if($lead->email)
                                <div class="contact-info has-email">
                                    <i class="ri-mail-line"></i>
                                    <span>{{ $lead->email }}</span>
                                    @if($lead->email_verified)
                                    <i class="ri-verified-badge-fill verified"></i>
                                    @endif
                                </div>
                                @else
                                <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                @if($lead->phone)
                                <div class="contact-info has-phone">
                                    <i class="ri-phone-line"></i>
                                    <span>{{ $lead->phone }}</span>
                                </div>
                                @else
                                <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                @if($lead->city || $lead->country)
                                <span class="text-muted">
                                    <i class="ri-map-pin-line me-1"></i>
                                    {{ $lead->city }}{{ $lead->city && $lead->country ? ', ' : '' }}{{ $lead->country }}
                                </span>
                                @else
                                <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                @php
                                    $qualityClass = $lead->quality_score >= 80 ? 'excellent' : ($lead->quality_score >= 60 ? 'good' : ($lead->quality_score >= 40 ? 'fair' : 'poor'));
                                @endphp
                                <span class="quality-badge {{ $qualityClass }}">
                                    <i class="ri-star-fill"></i> {{ $lead->quality_score }}%
                                </span>
                            </td>
                            <td>
                                @if($lead->isImported())
                                <span class="status-badge imported">
                                    <i class="ri-check-line"></i> {{ translate('Imported') }}
                                </span>
                                @else
                                <span class="status-badge pending">
                                    <i class="ri-time-line"></i> {{ translate('Pending') }}
                                </span>
                                @endif
                            </td>
                            <td>
                                @if($lead->job)
                                @php
                                    $sourceClass = $lead->job->type->value == 'google_maps' ? 'maps' : 'website';
                                    $sourceIcon = $lead->job->type->value == 'google_maps' ? 'ri-map-pin-line' : 'ri-global-line';
                                @endphp
                                <a href="{{ route('admin.lead-generation.results', $lead->job->uid) }}" class="source-badge {{ $sourceClass }}">
                                    <i class="{{ $sourceIcon }}"></i> {{ $lead->job->type->label() }}
                                </a>
                                @else
                                <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                <div class="action-buttons justify-content-end">
                                    <button class="action-btn view view-lead" data-lead="{{ json_encode($lead) }}" data-bs-toggle="modal" data-bs-target="#viewLeadModal" title="{{ translate('View Details') }}">
                                        <i class="ri-eye-line"></i>
                                    </button>
                                    @if(!$lead->isImported())
                                    <button class="action-btn success save-to-contact" data-id="{{ $lead->id }}" title="{{ translate('Save to Contacts') }}">
                                        <i class="ri-user-add-line"></i>
                                    </button>
                                    @endif
                                    <button class="action-btn delete delete-lead" data-id="{{ $lead->id }}" title="{{ translate('Delete') }}">
                                        <i class="ri-delete-bin-line"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($leads->hasPages())
            <div class="pagination-wrapper">
                {{ $leads->appends(request()->query())->links() }}
            </div>
            @endif

            @else
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="ri-contacts-book-line"></i>
                </div>
                <h5>{{ translate('No Leads Found') }}</h5>
                <p>{{ translate('Start scraping to collect leads from Google Maps or websites.') }}</p>
                <a href="{{ route('admin.lead-generation.index') }}" class="i-btn btn--primary btn--md">
                    <i class="ri-add-line me-1"></i> {{ translate('Start Scraping') }}
                </a>
            </div>
            @endif
        </div>
    </div>
</main>
@endsection

@section('modal')
{{-- Import Modal --}}
<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="ri-download-2-line me-2 text-primary"></i>{{ translate('Import Leads to Contacts') }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="importForm">
                    <div class="mb-4">
                        <label class="form-label fw-semibold">{{ translate('Select Contact Group') }} <span class="text-danger">*</span></label>
                        <select class="form-select select2-search" name="group_id" required>
                            <option value="">{{ translate('Choose a group...') }}</option>
                            @foreach($groups as $group)
                            <option value="{{ $group->id }}">{{ $group->name }}</option>
                            @endforeach
                        </select>
                        <small class="text-muted">{{ translate('Leads will be added to this contact group') }}</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">{{ translate('Import As') }}</label>
                        <select class="form-select" name="import_type" required>
                            <option value="all">{{ translate('All contact types') }}</option>
                            <option value="email">{{ translate('Email contacts only') }}</option>
                            <option value="sms">{{ translate('SMS contacts only') }}</option>
                            <option value="whatsapp">{{ translate('WhatsApp contacts only') }}</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="i-btn btn--dark outline btn--md" data-bs-dismiss="modal">
                    {{ translate('Cancel') }}
                </button>
                <button type="button" class="i-btn btn--primary btn--md" id="confirmImport">
                    <i class="ri-download-2-line me-1"></i> {{ translate('Import Leads') }}
                </button>
            </div>
        </div>
    </div>
</div>

{{-- View Lead Modal --}}
<div class="modal fade" id="viewLeadModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="ri-user-line me-2 text-primary"></i>{{ translate('Lead Details') }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="leadDetails"></div>
            </div>
        </div>
    </div>
</div>

{{-- Delete Confirmation Modal --}}
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content">
            <div class="modal-body text-center py-4">
                <div class="delete-icon-wrapper mb-3">
                    <div class="delete-icon">
                        <i class="ri-delete-bin-line"></i>
                    </div>
                </div>
                <h5 class="mb-2">{{ translate('Delete Lead?') }}</h5>
                <p class="text-muted mb-4" id="deleteModalText">{{ translate('This action cannot be undone.') }}</p>
                <div class="d-flex gap-2 justify-content-center">
                    <button type="button" class="i-btn btn--dark outline btn--md" data-bs-dismiss="modal">
                        {{ translate('Cancel') }}
                    </button>
                    <button type="button" class="i-btn btn--danger btn--md" id="confirmDeleteBtn">
                        <i class="ri-delete-bin-line me-1"></i> {{ translate('Delete') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
{{-- Quick Save to Contact Modal --}}
<div class="modal fade" id="quickSaveModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ translate('Save to Contacts') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">{{ translate('Contact Group') }} <small class="text-danger">*</small></label>
                    <select class="form-select" id="quickSaveGroupId" required>
                        <option value="">{{ translate('Select Group') }}</option>
                        @foreach($groups as $group)
                            <option value="{{ $group->id }}">{{ $group->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="i-btn btn--dark outline btn--md" data-bs-dismiss="modal">{{ translate('Cancel') }}</button>
                <button type="button" class="i-btn btn--success btn--md" id="confirmQuickSave">
                    <i class="ri-check-line"></i> {{ translate('Save') }}
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push("script-include")
<script src="{{asset('assets/theme/global/js/select2.min.js')}}"></script>
@endpush

@push('script-push')
<script>
(function($) {
    "use strict";

    // Initialize Select2
    if (typeof select2_search === 'function') {
        select2_search($('.select2-search').data('placeholder'));
    }

    var selectedLeads = [];

    // Select All
    $('#selectAll').on('change', function() {
        $('.lead-checkbox').prop('checked', $(this).prop('checked'));
        updateSelectedLeads();
    });

    // Individual checkbox
    $(document).on('change', '.lead-checkbox', function() {
        updateSelectedLeads();
    });

    function updateSelectedLeads() {
        selectedLeads = [];
        $('.lead-checkbox:checked').each(function() {
            selectedLeads.push($(this).val());
        });

        if (selectedLeads.length > 0) {
            $('#bulkActionBar').removeClass('d-none');
            $('#selectedCount').text(selectedLeads.length);
        } else {
            $('#bulkActionBar').addClass('d-none');
        }
    }

    // Import leads
    $('#confirmImport').on('click', function() {
        if (selectedLeads.length === 0) {
            notify('error', '{{ translate("Please select at least one lead") }}');
            return;
        }

        var groupId = $('#importForm select[name=group_id]').val();
        var importType = $('#importForm select[name=import_type]').val();

        if (!groupId) {
            notify('error', '{{ translate("Please select a contact group") }}');
            return;
        }

        var btn = $(this);
        btn.prop('disabled', true).html('<i class="ri-loader-4-line ri-spin me-1"></i> {{ translate("Importing...") }}');

        $.ajax({
            url: '{{ route("admin.lead-generation.leads.import") }}',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                lead_ids: selectedLeads,
                group_id: groupId,
                import_type: importType
            },
            success: function(response) {
                if (response.status) {
                    notify('success', response.message);
                    location.reload();
                } else {
                    notify('error', response.message);
                    btn.prop('disabled', false).html('<i class="ri-download-2-line me-1"></i> {{ translate("Import Leads") }}');
                }
            },
            error: function() {
                notify('error', '{{ translate("An error occurred") }}');
                btn.prop('disabled', false).html('<i class="ri-download-2-line me-1"></i> {{ translate("Import Leads") }}');
            }
        });
    });

    // Delete modal variables
    var deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
    var deleteType = null;
    var deleteId = null;

    // Delete single lead - show modal
    $(document).on('click', '.delete-lead', function() {
        deleteType = 'single';
        deleteId = $(this).data('id');
        $('#deleteModalText').text('{{ translate("This action cannot be undone.") }}');
        $('h5', '#deleteModal').text('{{ translate("Delete Lead?") }}');
        deleteModal.show();
    });

    // Bulk delete - show modal
    $('#bulkDeleteBtn').on('click', function() {
        deleteType = 'bulk';
        deleteId = null;
        $('#deleteModalText').text('{{ translate("You are about to delete") }} ' + selectedLeads.length + ' {{ translate("leads. This action cannot be undone.") }}');
        $('h5', '#deleteModal').text('{{ translate("Delete Selected Leads?") }}');
        deleteModal.show();
    });

    // Confirm delete
    $('#confirmDeleteBtn').on('click', function() {
        var btn = $(this);
        btn.prop('disabled', true).html('<i class="ri-loader-4-line ri-spin me-1"></i> {{ translate("Deleting...") }}');

        if (deleteType === 'single') {
            // Single lead delete
            $.ajax({
                url: '{{ route("admin.lead-generation.lead.delete", "") }}/' + deleteId,
                method: 'DELETE',
                data: { _token: '{{ csrf_token() }}' },
                success: function(response) {
                    deleteModal.hide();
                    if (response.status) {
                        notify('success', response.message);
                        $('[data-id="' + deleteId + '"]').closest('tr').fadeOut(300, function() { $(this).remove(); });
                    } else {
                        notify('error', response.message);
                    }
                    resetDeleteBtn();
                },
                error: function() {
                    deleteModal.hide();
                    notify('error', '{{ translate("An error occurred") }}');
                    resetDeleteBtn();
                }
            });
        } else if (deleteType === 'bulk') {
            // Bulk delete
            $.ajax({
                url: '{{ route("admin.lead-generation.leads.bulk-delete") }}',
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    lead_ids: selectedLeads
                },
                success: function(response) {
                    deleteModal.hide();
                    if (response.status) {
                        notify('success', response.message);
                        location.reload();
                    } else {
                        notify('error', response.message);
                        resetDeleteBtn();
                    }
                },
                error: function() {
                    deleteModal.hide();
                    notify('error', '{{ translate("An error occurred") }}');
                    resetDeleteBtn();
                }
            });
        }
    });

    function resetDeleteBtn() {
        $('#confirmDeleteBtn').prop('disabled', false).html('<i class="ri-delete-bin-line me-1"></i> {{ translate("Delete") }}');
    }

    // Reset on modal hide
    $('#deleteModal').on('hidden.bs.modal', function() {
        resetDeleteBtn();
        deleteType = null;
        deleteId = null;
    });

    // View lead details
    $(document).on('click', '.view-lead', function() {
        var lead = $(this).data('lead');

        var html = '<div class="lead-detail-grid">';

        var fields = [
            { label: '{{ translate("Business Name") }}', value: lead.business_name, icon: 'ri-building-line' },
            { label: '{{ translate("Email") }}', value: lead.email, icon: 'ri-mail-line' },
            { label: '{{ translate("Phone") }}', value: lead.phone, icon: 'ri-phone-line' },
            { label: '{{ translate("Website") }}', value: lead.website, icon: 'ri-global-line' },
            { label: '{{ translate("Address") }}', value: lead.address, icon: 'ri-map-pin-line' },
            { label: '{{ translate("City") }}', value: lead.city, icon: 'ri-building-2-line' },
            { label: '{{ translate("Country") }}', value: lead.country, icon: 'ri-earth-line' },
            { label: '{{ translate("Category") }}', value: lead.category, icon: 'ri-price-tag-3-line' },
            { label: '{{ translate("Rating") }}', value: lead.rating ? lead.rating + ' / 5' : null, icon: 'ri-star-line' },
            { label: '{{ translate("Quality Score") }}', value: lead.quality_score + '%', icon: 'ri-bar-chart-line' }
        ];

        fields.forEach(function(field) {
            if (field.value) {
                html += '<div class="lead-detail-item">';
                html += '<label><i class="' + field.icon + ' me-1"></i>' + field.label + '</label>';
                html += '<span>' + field.value + '</span>';
                html += '</div>';
            }
        });

        html += '</div>';

        $('#leadDetails').html(html);
    });

    // Quick save single lead to contacts
    var quickSaveLeadId = null;

    $(document).on('click', '.save-to-contact', function() {
        quickSaveLeadId = $(this).data('id');
        $('#quickSaveModal').modal('show');
    });

    $('#confirmQuickSave').on('click', function() {
        var groupId = $('#quickSaveGroupId').val();
        if (!groupId) {
            notify('error', '{{ translate("Select a group") }}');
            return;
        }

        var btn = $(this);
        btn.prop('disabled', true).html('<i class="ri-loader-2-line ri-spin"></i>');

        $.post('{{ route("admin.lead-generation.leads.import") }}', {
            _token: '{{ csrf_token() }}',
            lead_ids: [quickSaveLeadId],
            group_id: groupId,
            import_type: 'all'
        }, function(response) {
            if (response.status) {
                notify('success', response.message || '{{ translate("Lead saved to contacts") }}');
                $('#quickSaveModal').modal('hide');
                setTimeout(function() { location.reload(); }, 1000);
            } else {
                notify('error', response.message || '{{ translate("Failed") }}');
            }
        }).fail(function() {
            notify('error', '{{ translate("Failed to save lead") }}');
        }).always(function() {
            btn.prop('disabled', false).html('<i class="ri-check-line"></i> {{ translate("Save") }}');
        });
    });

})(jQuery);
</script>
@endpush
