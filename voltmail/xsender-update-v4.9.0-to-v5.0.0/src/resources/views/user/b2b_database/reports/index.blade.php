@extends('user.layouts.app')
@section('panel')

@push('style-push')
<style>
    .b2b-stat {
        background: var(--card-bg, #fff); border-radius: 12px; padding: 24px;
        border: 1px solid var(--border-color, #e5e7eb); display: flex; align-items: center; gap: 16px;
    }
    .b2b-stat-icon {
        width: 52px; height: 52px; border-radius: 12px; display: flex;
        align-items: center; justify-content: center; font-size: 22px; flex-shrink: 0;
    }
    .b2b-stat-label { font-size: 13px; color: #6b7280; margin-bottom: 4px; }
    .b2b-stat-value { font-size: 24px; font-weight: 700; color: #1f2937; line-height: 1; }

    .b2b-table { width: 100%; border-collapse: separate; border-spacing: 0; }
    .b2b-table th {
        text-align: left; padding: 12px 16px; font-size: 12px; font-weight: 600;
        color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px;
        background: #f9fafb; border-bottom: 1px solid #e5e7eb;
    }
    .b2b-table th:first-child { border-radius: 8px 0 0 0; }
    .b2b-table th:last-child { border-radius: 0 8px 0 0; }
    .b2b-table td { padding: 14px 16px; border-bottom: 1px solid #f3f4f6; font-size: 13px; color: #374151; vertical-align: middle; }
    .b2b-table tr:last-child td { border-bottom: none; }
    .b2b-table tr:hover td { background: #fafbfc; }

    .b2b-provider-badge {
        display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px;
        border-radius: 6px; font-size: 11px; font-weight: 600;
    }
    .b2b-provider-badge--apollo { background: #eef2ff; color: #4338ca; }
    .b2b-provider-badge--hunter { background: #fef3c7; color: #b45309; }
    .b2b-provider-badge--snov { background: #ecfdf5; color: #059669; }
    .b2b-provider-badge--rocketreach { background: #fce7f3; color: #be185d; }

    .b2b-status { display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
    .b2b-status--completed { background: #ecfdf5; color: #059669; }
    .b2b-status--failed { background: #fef2f2; color: #dc2626; }
    .b2b-status--pending { background: #fefce8; color: #ca8a04; }

    .b2b-filter-tag {
        display: inline-block; padding: 2px 8px; border-radius: 4px;
        background: #f3f4f6; color: #6b7280; font-size: 11px; margin: 1px 2px;
    }

    .b2b-empty { text-align: center; padding: 48px 24px; }
    .b2b-empty i { font-size: 40px; color: #d1d5db; }
    .b2b-empty h5 { margin-top: 12px; color: #9ca3af; font-weight: 600; }
    .b2b-empty p { color: #d1d5db; font-size: 13px; }
</style>
@endpush

<main class="main-body">
    <div class="container-fluid px-0 main-content">
        <div class="page-header">
            <div class="page-header-left">
                <h2>{{ translate("B2B Database Reports") }}</h2>
                <div class="breadcrumb-wrapper">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('user.dashboard') }}">{{ translate("Dashboard") }}</a></li>
                            <li class="breadcrumb-item active">{{ translate("B2B Reports") }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>

        {{-- Stats --}}
        <div class="row g-3 mb-4">
            <div class="col-xl-4 col-md-6">
                <div class="b2b-stat">
                    <div class="b2b-stat-icon" style="background: rgba(99,102,241,0.1);">
                        <i class="ri-search-2-line" style="color: #6366f1;"></i>
                    </div>
                    <div>
                        <div class="b2b-stat-label">{{ translate("Total Searches") }}</div>
                        <div class="b2b-stat-value">{{ number_format($stats['total_searches']) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-md-6">
                <div class="b2b-stat">
                    <div class="b2b-stat-icon" style="background: rgba(16,185,129,0.1);">
                        <i class="ri-user-received-line" style="color: #10b981;"></i>
                    </div>
                    <div>
                        <div class="b2b-stat-label">{{ translate("Contacts Imported") }}</div>
                        <div class="b2b-stat-value">{{ number_format($stats['total_imported']) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-md-6">
                <div class="b2b-stat">
                    <div class="b2b-stat-icon" style="background: rgba(245,158,11,0.1);">
                        <i class="ri-vip-diamond-line" style="color: #f59e0b;"></i>
                    </div>
                    <div>
                        <div class="b2b-stat-label">{{ translate("API Credits Used") }}</div>
                        <div class="b2b-stat-value">{{ number_format($stats['total_credits']) }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Table --}}
        <div class="card">
            <div class="card-header" style="display: flex; align-items: center; justify-content: space-between;">
                <h4 class="card-title mb-0">{{ translate("Search History") }}</h4>
            </div>
            <div class="card-body pt-0 px-0">
                @if($logs->count() > 0)
                <table class="b2b-table">
                    <thead>
                        <tr>
                            <th>{{ translate("Date") }}</th>
                            <th>{{ translate("Provider") }}</th>
                            <th>{{ translate("Search Filters") }}</th>
                            <th style="text-align: center;">{{ translate("Found") }}</th>
                            <th style="text-align: center;">{{ translate("Imported") }}</th>
                            <th style="text-align: center;">{{ translate("Credits") }}</th>
                            <th>{{ translate("Group") }}</th>
                            <th>{{ translate("Status") }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($logs as $log)
                        <tr>
                            <td>
                                <div style="font-weight: 500;">{{ $log->created_at->format('M d, Y') }}</div>
                                <small class="text-muted">{{ $log->created_at->format('h:i A') }}</small>
                            </td>
                            <td>
                                @php $pType = is_object($log->provider_type) ? $log->provider_type->value : $log->provider_type; @endphp
                                <span class="b2b-provider-badge b2b-provider-badge--{{ $pType }}">
                                    {{ ucfirst($pType) }}
                                </span>
                            </td>
                            <td>
                                @php $filters = is_array($log->search_filters) ? $log->search_filters : json_decode($log->search_filters ?? '{}', true); @endphp
                                @forelse(collect($filters)->take(3) as $k => $v)
                                    @if(!empty($v))
                                    <span class="b2b-filter-tag">{{ $k }}: {{ is_array($v) ? implode(', ', $v) : $v }}</span>
                                    @endif
                                @empty
                                    <span class="text-muted">-</span>
                                @endforelse
                            </td>
                            <td style="text-align: center; font-weight: 600;">{{ number_format($log->total_results) }}</td>
                            <td style="text-align: center; font-weight: 600; color: #10b981;">{{ $log->imported_count }}</td>
                            <td style="text-align: center;">{{ $log->credits_used }}</td>
                            <td>
                                @if($log->group)
                                    <span style="font-size: 12px;">{{ $log->group->name }}</span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                @if($log->status === 'completed')
                                    <span class="b2b-status b2b-status--completed"><i class="ri-checkbox-circle-line"></i> {{ translate("Done") }}</span>
                                @elseif($log->status === 'failed')
                                    <span class="b2b-status b2b-status--failed"><i class="ri-close-circle-line"></i> {{ translate("Failed") }}</span>
                                @else
                                    <span class="b2b-status b2b-status--pending"><i class="ri-time-line"></i> {{ ucfirst($log->status) }}</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="px-3 pt-2">{{ $logs->links() }}</div>
                @else
                <div class="b2b-empty">
                    <i class="ri-bar-chart-grouped-line"></i>
                    <h5>{{ translate("No Search History Yet") }}</h5>
                    <p>{{ translate("Search results will appear here after you use the B2B contact search.") }}</p>
                    <a href="{{ route('user.b2b-database.search') }}" class="i-btn btn--primary btn--sm mt-2">
                        <i class="ri-search-line"></i> {{ translate("Start Searching") }}
                    </a>
                </div>
                @endif
            </div>
        </div>
    </div>
</main>

@endsection
