@extends('admin.layouts.app')
@section('panel')

@push('style-push')
<style>
    .dbh-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
    .dbh-stat {
        background: var(--card-bg, #fff); border-radius: 12px; padding: 20px 24px;
        border: 1px solid var(--border-color, #e5e7eb); display: flex; align-items: center; gap: 16px;
    }
    .dbh-stat-icon {
        width: 48px; height: 48px; border-radius: 12px; display: flex;
        align-items: center; justify-content: center; font-size: 22px; flex-shrink: 0;
    }
    .dbh-stat-label { font-size: 13px; color: #6b7280; margin-bottom: 2px; }
    .dbh-stat-value { font-size: 22px; font-weight: 700; color: #1f2937; }

    .dbh-table { width: 100%; border-collapse: separate; border-spacing: 0; }
    .dbh-table th {
        text-align: left; padding: 12px 16px; font-size: 12px; font-weight: 600;
        color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px;
        background: #f9fafb; border-bottom: 1px solid #e5e7eb;
    }
    .dbh-table th:first-child { border-radius: 8px 0 0 0; }
    .dbh-table th:last-child { border-radius: 0 8px 0 0; }
    .dbh-table td { padding: 14px 16px; border-bottom: 1px solid #f3f4f6; font-size: 13px; color: #374151; }
    .dbh-table tr:last-child td { border-bottom: none; }
    .dbh-table tr:hover td { background: #fafbfc; }

    .dbh-badge {
        display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px;
        border-radius: 6px; font-size: 11px; font-weight: 600;
    }
    .dbh-badge--danger { background: #fef2f2; color: #dc2626; }
    .dbh-badge--warning { background: #fefce8; color: #ca8a04; }
    .dbh-badge--info { background: #eff6ff; color: #2563eb; }

    .dbh-how {
        display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-top: 24px;
    }
    .dbh-how-item {
        display: flex; align-items: flex-start; gap: 10px; padding: 14px 16px;
        background: #f9fafb; border-radius: 10px; font-size: 12.5px; color: #4b5563; line-height: 1.5;
    }
    .dbh-how-icon { color: #6366f1; font-size: 16px; margin-top: 1px; flex-shrink: 0; }

    .dbh-healthy {
        text-align: center; padding: 48px 24px; background: #f0fdf4; border-radius: 12px; border: 1px solid #bbf7d0;
    }
    .dbh-healthy i { font-size: 48px; color: #16a34a; }
    .dbh-healthy h4 { margin-top: 12px; font-weight: 700; color: #15803d; }
    .dbh-healthy p { color: #4ade80; font-size: 14px; }

    @media (max-width: 768px) {
        .dbh-stats { grid-template-columns: repeat(2, 1fr); }
        .dbh-how { grid-template-columns: 1fr; }
    }
</style>
@endpush

<main class="main-body">
    <div class="container-fluid px-0 main-content">
        <div class="page-header">
            <div class="page-header-left">
                <h2>{{ $title }}</h2>
                <div class="breadcrumb-wrapper">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ translate("Dashboard") }}</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('admin.system.update.init') }}">{{ translate("System Update") }}</a></li>
                            <li class="breadcrumb-item active">{{ translate("Database Health") }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
            <div class="page-header-right">
                @if($totalIssues > 0)
                <button type="button" class="i-btn btn--success btn--md" id="fixAllBtn">
                    <i class="ri-tools-line"></i> {{ translate("Fix All Issues") }} ({{ $totalIssues }})
                </button>
                @endif
            </div>
        </div>

        {{-- Stats --}}
        <div class="dbh-stats">
            <div class="dbh-stat">
                <div class="dbh-stat-icon" style="background: {{ $totalIssues === 0 ? 'rgba(22,163,74,0.1)' : 'rgba(220,38,38,0.1)' }};">
                    <i class="ri-{{ $totalIssues === 0 ? 'shield-check' : 'error-warning' }}-line" style="color: {{ $totalIssues === 0 ? '#16a34a' : '#dc2626' }};"></i>
                </div>
                <div>
                    <div class="dbh-stat-label">{{ translate("Status") }}</div>
                    <div class="dbh-stat-value">{{ $totalIssues === 0 ? translate("Healthy") : $totalIssues . ' ' . translate("Issues") }}</div>
                </div>
            </div>
            <div class="dbh-stat">
                <div class="dbh-stat-icon" style="background: rgba(245,158,11,0.1);"><i class="ri-table-line" style="color: #f59e0b;"></i></div>
                <div>
                    <div class="dbh-stat-label">{{ translate("Missing Tables") }}</div>
                    <div class="dbh-stat-value">{{ count($issues['missing_tables']) }}</div>
                </div>
            </div>
            <div class="dbh-stat">
                <div class="dbh-stat-icon" style="background: rgba(6,182,212,0.1);"><i class="ri-layout-column-line" style="color: #06b6d4;"></i></div>
                <div>
                    <div class="dbh-stat-label">{{ translate("Missing Columns") }}</div>
                    <div class="dbh-stat-value">{{ count($issues['missing_columns']) }}</div>
                </div>
            </div>
            <div class="dbh-stat">
                <div class="dbh-stat-icon" style="background: rgba(99,102,241,0.1);"><i class="ri-exchange-line" style="color: #6366f1;"></i></div>
                <div>
                    <div class="dbh-stat-label">{{ translate("Type Mismatches") }}</div>
                    <div class="dbh-stat-value">{{ count($issues['type_mismatches']) }}</div>
                </div>
            </div>
        </div>

        @if(!$hasSnapshot)
            <div class="card">
                <div class="card-body p-4">
                    <div style="display: flex; align-items: center; gap: 12px; color: #92400e;">
                        <i class="ri-error-warning-line fs-4"></i>
                        <span>{{ translate("No schema snapshot found. This is automatically included in update packages.") }}</span>
                    </div>
                </div>
            </div>
        @elseif($totalIssues === 0)
            <div class="dbh-healthy">
                <i class="ri-checkbox-circle-line"></i>
                <h4>{{ translate("Database is Healthy") }}</h4>
                <p>{{ translate("All tables, columns, and types match the expected schema.") }}</p>
            </div>
        @else
            {{-- Issues Table --}}
            <div class="card">
                <div class="card-header" style="display: flex; align-items: center; justify-content: space-between;">
                    <h4 class="card-title mb-0">{{ translate("Issues Found") }}</h4>
                    <small class="text-muted">{{ translate("Click 'Fix All Issues' to resolve automatically. No data will be lost.") }}</small>
                </div>
                <div class="card-body pt-0 px-0">
                    <table class="dbh-table">
                        <thead>
                            <tr>
                                <th style="width: 160px;">{{ translate("Type") }}</th>
                                <th>{{ translate("Table") }}</th>
                                <th>{{ translate("Column") }}</th>
                                <th>{{ translate("Details") }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($issues['missing_tables'] as $table)
                            <tr>
                                <td><span class="dbh-badge dbh-badge--danger"><i class="ri-close-circle-line"></i> {{ translate("Missing Table") }}</span></td>
                                <td><code>{{ $table }}</code></td>
                                <td><span class="text-muted">{{ translate("All columns") }}</span></td>
                                <td>{{ translate("Will be created with all columns and indexes") }}</td>
                            </tr>
                            @endforeach
                            @foreach($issues['missing_columns'] as $col)
                            <tr>
                                <td><span class="dbh-badge dbh-badge--warning"><i class="ri-add-circle-line"></i> {{ translate("Missing Column") }}</span></td>
                                <td><code>{{ $col['table'] }}</code></td>
                                <td><code>{{ $col['column'] }}</code></td>
                                <td>{{ translate("Expected") }}: <code>{{ $col['type'] }}</code></td>
                            </tr>
                            @endforeach
                            @foreach($issues['type_mismatches'] as $m)
                            <tr>
                                <td><span class="dbh-badge dbh-badge--info"><i class="ri-exchange-line"></i> {{ translate("Type Mismatch") }}</span></td>
                                <td><code>{{ $m['table'] }}</code></td>
                                <td><code>{{ $m['column'] }}</code></td>
                                <td><code style="color: #dc2626;">{{ $m['actual'] }}</code> <i class="ri-arrow-right-line mx-1" style="color: #9ca3af;"></i> <code style="color: #16a34a;">{{ $m['expected'] }}</code></td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- How It Works --}}
        <div class="dbh-how">
            <div class="dbh-how-item">
                <i class="ri-add-box-line dbh-how-icon"></i>
                <span>{{ translate("Missing tables & columns are created automatically without affecting existing data") }}</span>
            </div>
            <div class="dbh-how-item">
                <i class="ri-exchange-line dbh-how-icon"></i>
                <span>{{ translate("Type mismatches (enum, int→bigint) are corrected. Invalid dates (0000-00-00) fixed to NULL") }}</span>
            </div>
            <div class="dbh-how-item">
                <i class="ri-shield-check-line dbh-how-icon"></i>
                <span><strong>{{ translate("No data is ever deleted") }}</strong> — {{ translate("foreign keys are handled, only structure is modified") }}</span>
            </div>
        </div>
    </div>
</main>

{{-- Custom Confirmation Modal --}}
<div class="modal fade" id="fixConfirmModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content" style="border: none; border-radius: 16px; overflow: hidden;">
            <div class="modal-body text-center" style="padding: 36px 32px;">
                <div style="width: 64px; height: 64px; border-radius: 50%; background: rgba(16,185,129,0.1); display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
                    <i class="ri-stethoscope-line" style="font-size: 28px; color: #10b981;"></i>
                </div>
                <h5 style="font-weight: 700; margin-bottom: 8px;">{{ translate("Fix Database Issues?") }}</h5>
                <p style="color: #6b7280; font-size: 14px; margin-bottom: 24px; line-height: 1.6;">
                    {{ translate("This will automatically repair") }} <strong>{{ $totalIssues }} {{ translate("issue(s)") }}</strong>.
                    <br>{{ translate("Missing tables and columns will be created. Type mismatches will be corrected.") }}
                    <br><strong style="color: #059669;">{{ translate("No data will be deleted.") }}</strong>
                </p>
                <form action="{{ route('admin.system.database.health.fix') }}" method="POST" id="fixForm">
                    @csrf
                    <div style="display: flex; gap: 12px; justify-content: center;">
                        <button type="button" class="i-btn btn--dark outline btn--md" data-bs-dismiss="modal" style="min-width: 120px;">
                            {{ translate("Cancel") }}
                        </button>
                        <button type="submit" class="i-btn btn--success btn--md" id="confirmFixBtn" style="min-width: 120px;">
                            <i class="ri-tools-line"></i> {{ translate("Fix Now") }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection

@push('script-push')
<script>
(function($) {
    "use strict";

    $('#fixAllBtn').on('click', function() {
        $('#fixConfirmModal').modal('show');
    });

    $('#fixForm').on('submit', function() {
        $('#confirmFixBtn').prop('disabled', true).html('<i class="ri-loader-2-line ri-spin"></i> {{ translate("Fixing...") }}');
    });

})(jQuery);
</script>
@endpush
