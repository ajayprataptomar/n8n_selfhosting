@extends('admin.layouts.app')
@section('panel')
<main class="main-body">
    <div class="container-fluid px-0 main-content">
        <div class="page-header">
            <div class="page-header-left">
                <h2>{{ $title }}</h2>
                <div class="breadcrumb-wrapper">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ translate('Dashboard') }}</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('admin.lead-generation.index') }}">{{ translate('Lead Generation') }}</a></li>
                            <li class="breadcrumb-item active">{{ translate('Meta Lead Ads') }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
            <div class="page-header-right">
                <a href="{{ route('admin.lead-generation.meta-lead-ads.create') }}" class="i-btn btn--primary btn--md">
                    <i class="ri-add-line"></i> {{ translate('Add Subscription') }}
                </a>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <h6 class="mb-2"><i class="ri-information-line"></i> {{ translate('Webhook Configuration') }}</h6>
                <p class="text-muted small mb-2">
                    {{ translate('Configure these values in your Meta App webhook settings (developers.facebook.com → your app → Webhooks → Page → leadgen):') }}
                </p>
                <table class="table table-sm mb-0">
                    <tr>
                        <td class="text-muted" style="width:200px;">{{ translate('Callback URL') }}</td>
                        <td class="font-monospace small"><code>{{ $webhookUrl }}</code>
                            <button class="btn btn-sm btn-outline-secondary ms-2" onclick="navigator.clipboard.writeText('{{ $webhookUrl }}')"><i class="ri-clipboard-line"></i></button>
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted">{{ translate('Verify Token') }}</td>
                        <td class="font-monospace small">
                            @if($verifyToken)
                                <code>{{ $verifyToken }}</code>
                            @else
                                <em class="text-warning">{{ translate('Not set — configure in Settings → System → Meta Lead Ads verify token.') }}</em>
                            @endif
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4">{{ translate('Name') }}</th>
                                <th>{{ translate('Page / Form') }}</th>
                                <th>{{ translate('Group') }}</th>
                                <th>{{ translate('Workflow') }}</th>
                                <th>{{ translate('Leads') }}</th>
                                <th>{{ translate('Status') }}</th>
                                <th class="pe-4">{{ translate('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($subscriptions as $sub)
                            <tr>
                                <td class="ps-4">{{ $sub->name }}</td>
                                <td>
                                    <div class="small">{{ $sub->page_name ?: 'Page #' . $sub->page_id }}</div>
                                    <div class="small text-muted">{{ $sub->form_name ?: 'Form #' . $sub->form_id }}</div>
                                </td>
                                <td>{{ optional(\App\Models\ContactGroup::find($sub->contact_group_id))->name ?? '—' }}</td>
                                <td>{{ optional(\App\Models\Automation\AutomationWorkflow::find($sub->workflow_id))->name ?? '—' }}</td>
                                <td>{{ $sub->leads_imported }}</td>
                                <td>
                                    @if($sub->is_active)
                                        <span class="badge bg-success">{{ translate('Active') }}</span>
                                    @else
                                        <span class="badge bg-secondary">{{ translate('Paused') }}</span>
                                    @endif
                                </td>
                                <td class="pe-4">
                                    <a href="{{ route('admin.lead-generation.meta-lead-ads.edit', $sub->uid) }}" class="btn btn-sm btn-outline-info"><i class="ri-pencil-line"></i></a>
                                    <form action="{{ route('admin.lead-generation.meta-lead-ads.delete', $sub->uid) }}" method="POST" class="d-inline" onsubmit="return confirm('{{ translate('Delete this subscription?') }}');">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger"><i class="ri-delete-bin-line"></i></button>
                                    </form>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    {{ translate('No subscriptions yet. Add one to start importing leads from Facebook/Instagram.') }}
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($subscriptions->hasPages())
                <div class="p-3">{{ $subscriptions->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</main>
@endsection
