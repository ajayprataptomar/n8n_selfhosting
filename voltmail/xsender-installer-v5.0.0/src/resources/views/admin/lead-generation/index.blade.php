@extends('admin.layouts.app')
@push("style-include")
<style>
    /* Page Header Fix */
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

    /* Lead Generation Dashboard Styles */
    .lead-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 1rem;
    }

    .lead-stat-card {
        background: var(--card-bg, #fff);
        border-radius: 12px;
        padding: 1.25rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        border: 1px solid var(--border-color, #e9ecef);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .lead-stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 3px;
        height: 100%;
        border-radius: 4px 4px 4px 4px;
        opacity: 0.5;
    }

    .lead-stat-card.stat-primary::before { background: var(--color-primary); }
    .lead-stat-card.stat-success::before { background: var(--success-color, #10b981); }
    .lead-stat-card.stat-info::before { background: var(--info-color, #0ea5e9); }
    .lead-stat-card.stat-warning::before { background: var(--warning-color, #f59e0b); }
    .lead-stat-card.stat-danger::before { background: var(--danger-color, #ef4444); }
    .lead-stat-card.stat-secondary::before { background: var(--secondary-color, #6b7280); }

    .lead-stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
    }

    .lead-stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        flex-shrink: 0;
    }

    .lead-stat-icon.icon-primary { background: var(--color-primary-light); color: var(--color-primary); }
    .lead-stat-icon.icon-success { background: rgba(16, 185, 129, 0.1); color: var(--success-color, #10b981); }
    .lead-stat-icon.icon-info { background: rgba(14, 165, 233, 0.1); color: var(--info-color, #0ea5e9); }
    .lead-stat-icon.icon-warning { background: rgba(245, 158, 11, 0.1); color: var(--warning-color, #f59e0b); }
    .lead-stat-icon.icon-danger { background: rgba(239, 68, 68, 0.1); color: var(--danger-color, #ef4444); }
    .lead-stat-icon.icon-secondary { background: rgba(107, 114, 128, 0.1); color: var(--secondary-color, #6b7280); }

    .lead-stat-content h3 {
        font-size: 1.5rem;
        font-weight: 700;
        margin-bottom: 0.25rem;
        line-height: 1;
        color: var(--text-color, #1f2937);
    }

    .lead-stat-content p {
        font-size: 0.813rem;
        color: var(--text-muted, #6b7280);
        margin: 0;
        font-weight: 500;
    }

    /* Scraper Type Cards */
    .scraper-cards-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 1.5rem;
    }

    .scraper-card {
        background: var(--card-bg, #fff);
        border-radius: 16px;
        border: 1px solid var(--border-color, #e9ecef);
        transition: all 0.3s ease;
        overflow: hidden;
        position: relative;
    }

    .scraper-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 40px rgba(0, 0, 0, 0.1);
    }

    .scraper-card-header {
        padding: 1.5rem 1.5rem 0;
        position: relative;
    }

    .scraper-card-icon {
        width: 64px;
        height: 64px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.75rem;
        margin-bottom: 1rem;
    }

    .scraper-card-icon.icon-maps {
        background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
        color: var(--danger-color, #ef4444);
    }
    .scraper-card-icon.icon-website {
        background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
        color: var(--info-color, #3b82f6);
    }
    .scraper-card-icon.icon-enrich {
        background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
        color: var(--success-color, #10b981);
    }

    /* Primary soft icon button */
    .icon-btn.primary-soft {
        background: var(--color-primary-light);
        color: var(--color-primary);
    }
    .icon-btn.primary-soft:hover {
        background: var(--color-primary);
        color: #fff;
    }

    .scraper-card-body {
        padding: 0 1.5rem 1.5rem;
    }

    .scraper-card-title {
        font-size: 1.125rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
        color: var(--text-color, #1f2937);
    }

    .scraper-card-desc {
        font-size: 0.875rem;
        color: var(--text-muted, #6b7280);
        margin-bottom: 1.25rem;
        line-height: 1.5;
    }

    .scraper-card-features {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-bottom: 1.25rem;
    }

    .scraper-feature-tag {
        font-size: 0.75rem;
        padding: 0.25rem 0.75rem;
        background: var(--bg-light, #f3f4f6);
        border-radius: 20px;
        color: var(--text-muted, #6b7280);
        font-weight: 500;
    }

    .scraper-card-action .i-btn {
        width: 100%;
        justify-content: center;
        padding: 0.75rem 1.5rem;
        border-radius: 10px;
        font-weight: 600;
    }

    /* Jobs Table */
    .jobs-card .card-header {
        background: transparent;
        border-bottom: 1px solid var(--border-color, #e9ecef);
        padding: 1rem 1.25rem;
    }

    .jobs-card .card-header .card-title {
        font-size: 1rem;
        font-weight: 600;
    }

    .job-progress-bar {
        height: 6px;
        background: var(--bg-light, #e9ecef);
        border-radius: 3px;
        overflow: hidden;
        min-width: 80px;
    }

    .job-progress-bar .fill {
        height: 100%;
        border-radius: 3px;
        transition: width 0.3s ease;
    }

    .job-progress-bar .fill.bg-success { background: var(--success-color, #10b981); }
    .job-progress-bar .fill.bg-primary { background: var(--color-primary); }
    .job-progress-bar .fill.bg-warning { background: var(--warning-color, #f59e0b); }

    /* Empty State */
    .empty-jobs {
        padding: 3rem 1.5rem;
        text-align: center;
    }

    .empty-jobs-icon {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        background: var(--bg-light, #f3f4f6);
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1rem;
        font-size: 2rem;
        color: var(--text-muted, #6b7280);
    }

    .empty-jobs h5 {
        font-size: 1rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
        color: var(--text-color, #1f2937);
    }

    .empty-jobs p {
        color: var(--text-muted, #6b7280);
        font-size: 0.875rem;
        margin: 0;
    }

    /* Type Badges */
    .type-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
        padding: 0.375rem 0.75rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .type-badge.type-maps { background: rgba(239, 68, 68, 0.1); color: var(--danger-color, #ef4444); }
    .type-badge.type-website { background: rgba(59, 130, 246, 0.1); color: var(--info-color, #3b82f6); }
    .type-badge.type-enrich { background: rgba(16, 185, 129, 0.1); color: var(--success-color, #10b981); }

    /* Status Badges */
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        padding: 0.375rem 0.75rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .status-badge.status-pending { background: rgba(245, 158, 11, 0.1); color: var(--warning-color, #f59e0b); }
    .status-badge.status-processing { background: rgba(59, 130, 246, 0.1); color: var(--info-color, #3b82f6); }
    .status-badge.status-completed { background: rgba(16, 185, 129, 0.1); color: var(--success-color, #10b981); }
    .status-badge.status-failed { background: rgba(239, 68, 68, 0.1); color: var(--danger-color, #ef4444); }
    .status-badge.status-cancelled { background: rgba(107, 114, 128, 0.1); color: var(--secondary-color, #6b7280); }

    /* Dark Mode Support */
    [data-theme="dark"] .lead-stat-card,
    [data-theme="dark"] .scraper-card {
        background: var(--card-bg-dark, #1f2937);
        border-color: var(--border-color-dark, #374151);
    }

    [data-theme="dark"] .lead-stat-content h3,
    [data-theme="dark"] .scraper-card-title,
    [data-theme="dark"] .empty-jobs h5 {
        color: var(--text-color-dark, #f9fafb);
    }

    [data-theme="dark"] .scraper-feature-tag,
    [data-theme="dark"] .empty-jobs-icon {
        background: var(--bg-light-dark, #374151);
    }
</style>
@endpush

@section('panel')
<main class="main-body">
    <div class="container-fluid px-0 main-content">
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
                <a href="{{ route('admin.lead-generation.meta-lead-ads.index') }}" class="i-btn btn--info outline btn--md">
                    <i class="ri-facebook-circle-line"></i> {{ translate('Meta Lead Ads') }}
                </a>
                <a href="{{ route('admin.lead-generation.settings') }}" class="i-btn btn--dark outline btn--md">
                    <i class="ri-settings-3-line"></i> {{ translate('Settings') }}
                </a>
                <a href="{{ route('admin.lead-generation.leads') }}" class="i-btn btn--primary btn--md">
                    <i class="ri-contacts-book-line"></i> {{ translate('View All Leads') }}
                </a>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="lead-stats-grid mb-4">
            <div class="lead-stat-card stat-primary">
                <div class="lead-stat-icon icon-primary">
                    <i class="ri-team-line"></i>
                </div>
                <div class="lead-stat-content">
                    <h3>{{ number_format($stats['total_leads']) }}</h3>
                    <p>{{ translate('Total Leads') }}</p>
                </div>
            </div>
            <div class="lead-stat-card stat-success">
                <div class="lead-stat-icon icon-success">
                    <i class="ri-mail-line"></i>
                </div>
                <div class="lead-stat-content">
                    <h3>{{ number_format($stats['leads_with_email']) }}</h3>
                    <p>{{ translate('With Email') }}</p>
                </div>
            </div>
            <div class="lead-stat-card stat-info">
                <div class="lead-stat-icon icon-info">
                    <i class="ri-phone-line"></i>
                </div>
                <div class="lead-stat-content">
                    <h3>{{ number_format($stats['leads_with_phone']) }}</h3>
                    <p>{{ translate('With Phone') }}</p>
                </div>
            </div>
            <div class="lead-stat-card stat-warning">
                <div class="lead-stat-icon icon-warning">
                    <i class="ri-refresh-line"></i>
                </div>
                <div class="lead-stat-content">
                    <h3>{{ number_format($stats['total_jobs']) }}</h3>
                    <p>{{ translate('Total Jobs') }}</p>
                </div>
            </div>
            <div class="lead-stat-card stat-danger">
                <div class="lead-stat-icon icon-danger">
                    <i class="ri-hourglass-line"></i>
                </div>
                <div class="lead-stat-content">
                    <h3>{{ number_format($stats['pending_jobs']) }}</h3>
                    <p>{{ translate('Pending Jobs') }}</p>
                </div>
            </div>
            <div class="lead-stat-card stat-secondary">
                <div class="lead-stat-icon icon-secondary">
                    <i class="ri-checkbox-circle-line"></i>
                </div>
                <div class="lead-stat-content">
                    <h3>{{ number_format($stats['imported_leads']) }}</h3>
                    <p>{{ translate('Imported') }}</p>
                </div>
            </div>
        </div>

        <!-- Scraper Type Selection -->
        <div class="scraper-cards-grid mb-4">
            <div class="scraper-card">
                <div class="scraper-card-header">
                    <div class="scraper-card-icon icon-maps">
                        <i class="ri-map-pin-line"></i>
                    </div>
                </div>
                <div class="scraper-card-body">
                    <h4 class="scraper-card-title">{{ translate('Google Maps Scraper') }}</h4>
                    <p class="scraper-card-desc">{{ translate('Find businesses by location and category with contact details, ratings, and reviews.') }}</p>
                    <div class="scraper-card-features">
                        <span class="scraper-feature-tag">{{ translate('Business Info') }}</span>
                        <span class="scraper-feature-tag">{{ translate('Contact Details') }}</span>
                        <span class="scraper-feature-tag">{{ translate('Reviews') }}</span>
                    </div>
                    <div class="scraper-card-action">
                        <a href="{{ route('admin.lead-generation.scraper', 'google_maps') }}" class="i-btn btn--primary btn--md">
                            <i class="ri-play-circle-line"></i> {{ translate('Start Scraping') }}
                        </a>
                    </div>
                </div>
            </div>

            <div class="scraper-card">
                <div class="scraper-card-header">
                    <div class="scraper-card-icon icon-website">
                        <i class="ri-global-line"></i>
                    </div>
                </div>
                <div class="scraper-card-body">
                    <h4 class="scraper-card-title">{{ translate('Website Scraper') }}</h4>
                    <p class="scraper-card-desc">{{ translate('Extract emails, phone numbers, and social profiles directly from any website.') }}</p>
                    <div class="scraper-card-features">
                        <span class="scraper-feature-tag">{{ translate('Emails') }}</span>
                        <span class="scraper-feature-tag">{{ translate('Phone Numbers') }}</span>
                        <span class="scraper-feature-tag">{{ translate('Social Links') }}</span>
                    </div>
                    <div class="scraper-card-action">
                        <a href="{{ route('admin.lead-generation.scraper', 'website') }}" class="i-btn btn--info btn--md">
                            <i class="ri-play-circle-line"></i> {{ translate('Start Scraping') }}
                        </a>
                    </div>
                </div>
            </div>

            <div class="scraper-card">
                <div class="scraper-card-header">
                    <div class="scraper-card-icon icon-enrich">
                        <i class="ri-user-star-line"></i>
                    </div>
                </div>
                <div class="scraper-card-body">
                    <h4 class="scraper-card-title">{{ translate('Lead Enrichment') }}</h4>
                    <p class="scraper-card-desc">{{ translate('Validate and enrich your existing leads with additional data and verification.') }}</p>
                    <div class="scraper-card-features">
                        <span class="scraper-feature-tag">{{ translate('Validation') }}</span>
                        <span class="scraper-feature-tag">{{ translate('Enrichment') }}</span>
                        <span class="scraper-feature-tag">{{ translate('Quality Score') }}</span>
                    </div>
                    <div class="scraper-card-action">
                        <a href="{{ route('admin.lead-generation.leads') }}" class="i-btn btn--success btn--md">
                            <i class="ri-arrow-right-line"></i> {{ translate('View Leads') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Jobs Table -->
        <div class="card jobs-card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0">{{ translate('Recent Scraping Jobs') }}</h4>
                @if($jobs->count() > 0)
                <span class="text-muted small">{{ translate('Showing') }} {{ $jobs->count() }} {{ translate('jobs') }}</span>
                @endif
            </div>
            <div class="card-body px-0 pt-0">
                @if($jobs->count() > 0)
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>{{ translate('Job ID') }}</th>
                                <th>{{ translate('Type') }}</th>
                                <th>{{ translate('Query / Target') }}</th>
                                <th>{{ translate('Status') }}</th>
                                <th>{{ translate('Progress') }}</th>
                                <th>{{ translate('Leads Found') }}</th>
                                <th>{{ translate('Created') }}</th>
                                <th class="text-end">{{ translate('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($jobs as $job)
                            <tr>
                                <td>
                                    <code class="small">#{{ substr($job->uid, 0, 8) }}</code>
                                </td>
                                <td>
                                    @php
                                        $typeClass = match($job->type->value) {
                                            'google_maps' => 'type-maps',
                                            'website' => 'type-website',
                                            default => 'type-enrich'
                                        };
                                        $typeIcon = match($job->type->value) {
                                            'google_maps' => 'ri-map-pin-line',
                                            'website' => 'ri-global-line',
                                            default => 'ri-user-star-line'
                                        };
                                    @endphp
                                    <span class="type-badge {{ $typeClass }}">
                                        <i class="{{ $typeIcon }}"></i>
                                        {{ $job->type->label() }}
                                    </span>
                                </td>
                                <td>
                                    @if($job->type->value == 'google_maps')
                                        <strong>{{ $job->search_query ?? 'N/A' }}</strong>
                                        <br><small class="text-muted">{{ $job->location ?? 'N/A' }}</small>
                                    @elseif($job->type->value == 'website')
                                        <span title="{{ $job->parameters['urls'] ?? '' }}">
                                            {{ \Illuminate\Support\Str::limit($job->parameters['urls'] ?? '', 40) }}
                                        </span>
                                    @else
                                        {{ count($job->parameters['lead_ids'] ?? []) }} {{ translate('leads') }}
                                    @endif
                                </td>
                                <td>
                                    @php
                                        $statusClass = match($job->status->value) {
                                            'pending' => 'status-pending',
                                            'processing' => 'status-processing',
                                            'completed' => 'status-completed',
                                            'failed' => 'status-failed',
                                            default => 'status-cancelled'
                                        };
                                        $statusIcon = match($job->status->value) {
                                            'pending' => 'ri-time-line',
                                            'processing' => 'ri-loader-4-line',
                                            'completed' => 'ri-check-line',
                                            'failed' => 'ri-close-line',
                                            default => 'ri-forbid-line'
                                        };
                                    @endphp
                                    <span class="status-badge {{ $statusClass }}">
                                        <i class="{{ $statusIcon }}"></i>
                                        {{ $job->status->label() }}
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="job-progress-bar">
                                            @php
                                                $progressClass = $job->progress >= 100 ? 'bg-success' : ($job->progress > 0 ? 'bg-primary' : 'bg-warning');
                                            @endphp
                                            <div class="fill {{ $progressClass }}" style="width: {{ $job->progress }}%"></div>
                                        </div>
                                        <small class="text-muted">{{ $job->progress }}%</small>
                                    </div>
                                </td>
                                <td>
                                    <strong>{{ number_format($job->total_found) }}</strong>
                                </td>
                                <td>
                                    <span title="{{ $job->created_at->format('M d, Y H:i') }}">
                                        {{ $job->created_at->diffForHumans() }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="d-flex gap-1 justify-content-end">
                                        @if($job->isCompleted() && $job->total_found > 0)
                                            <a href="{{ route('admin.lead-generation.results', $job->uid) }}"
                                               class="icon-btn btn-ghost btn-sm primary-soft circle" title="{{ translate('View Results') }}">
                                                <i class="ri-eye-line"></i>
                                            </a>
                                        @endif
                                        @if($job->isRunning())
                                            <button class="icon-btn btn-ghost btn-sm danger-soft circle cancel-job"
                                                    data-uid="{{ $job->uid }}" title="{{ translate('Cancel Job') }}">
                                                <i class="ri-stop-circle-line"></i>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @include('admin.partials.pagination', ['paginator' => $jobs])
                @else
                <div class="empty-jobs">
                    <div class="empty-jobs-icon">
                        <i class="ri-search-eye-line"></i>
                    </div>
                    <h5>{{ translate('No Scraping Jobs Found') }}</h5>
                    <p>{{ translate('Start your first scraping job by selecting a scraper type above.') }}</p>
                </div>
                @endif
            </div>
        </div>

    </div>
</main>
@endsection

@push('script-push')
<script>
(function($) {
    "use strict";

    // Cancel job
    $('.cancel-job').on('click', function() {
        var uid = $(this).data('uid');
        var btn = $(this);

        if (!confirm('{{ translate("Are you sure you want to cancel this job?") }}')) {
            return;
        }

        btn.prop('disabled', true);

        $.ajax({
            url: '{{ route("admin.lead-generation.job.cancel", "") }}/' + uid,
            method: 'POST',
            data: { _token: '{{ csrf_token() }}' },
            success: function(response) {
                if (response.status) {
                    notify('success', response.message);
                    location.reload();
                } else {
                    notify('error', response.message);
                    btn.prop('disabled', false);
                }
            },
            error: function() {
                notify('error', '{{ translate("Failed to cancel job") }}');
                btn.prop('disabled', false);
            }
        });
    });

})(jQuery);
</script>
@endpush
