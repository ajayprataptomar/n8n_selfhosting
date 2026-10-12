@extends('user.layouts.app')
@section('panel')

<main class="main-body">
    <div class="container-fluid">
        <div class="page-header d-flex justify-content-between align-items-center mb-4">
            <div class="page-header-left">
                <h3 class="mb-1">{{ $resource->name }}</h3>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="{{ route('user.dashboard') }}">{{ translate("Dashboard") }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('user.content-resource.index') }}">{{ translate("Content Resources") }}</a></li>
                        <li class="breadcrumb-item active">{{ translate("Details") }}</li>
                    </ol>
                </nav>
            </div>
            <div class="page-header-right d-flex gap-2">
                <a href="{{ route('user.content-resource.edit', $resource->id) }}" class="i-btn btn--primary outline btn--md">
                    <i class="ri-edit-line me-1"></i> {{ translate("Edit Resource") }}
                </a>
                <a href="{{ route('user.content-resource.index') }}" class="i-btn btn--dark outline btn--md">
                    <i class="ri-arrow-left-line me-1"></i> {{ translate("Back to List") }}
                </a>
            </div>
        </div>

        <div class="row g-4">
            {{-- Left column: Resource profile details --}}
            <div class="col-lg-5">
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="card-title">{{ translate("Business Profile") }}</h4>
                        <span class="i-badge {{ $resource->status == 'active' ? 'success-soft' : 'warning-soft' }} pill">
                            {{ ucfirst($resource->status) }}
                        </span>
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item px-0 d-flex justify-content-between">
                                <span class="text-muted">{{ translate("Company Name") }}</span>
                                <span class="fw-semibold">{{ $resource->company_name }}</span>
                            </li>
                            <li class="list-group-item px-0 d-flex justify-content-between">
                                <span class="text-muted">{{ translate("Business Type") }}</span>
                                <span class="fw-medium">{{ $resource->business_type ?: '—' }}</span>
                            </li>
                            <li class="list-group-item px-0 d-flex justify-content-between">
                                <span class="text-muted">{{ translate("Website") }}</span>
                                <span>
                                    @if($resource->website)
                                        <a href="{{ $resource->website }}" target="_blank" class="text-primary">{{ $resource->website }}</a>
                                    @else
                                        —
                                    @endif
                                </span>
                            </li>
                            <li class="list-group-item px-0 d-flex justify-content-between">
                                <span class="text-muted">{{ translate("Industry") }}</span>
                                <span class="fw-medium">{{ $resource->industry ?: '—' }}</span>
                            </li>
                            <li class="list-group-item px-0 d-flex justify-content-between">
                                <span class="text-muted">{{ translate("Brand Voice") }}</span>
                                <span class="fw-medium">{{ $resource->brand_voice ?: '—' }}</span>
                            </li>
                            <li class="list-group-item px-0">
                                <span class="text-muted d-block mb-1">{{ translate("Description") }}</span>
                                <p class="mb-0 text-dark small">{{ $resource->company_description ?: translate('No description provided') }}</p>
                            </li>
                            <li class="list-group-item px-0">
                                <span class="text-muted d-block mb-1">{{ translate("Target Audience (ICP)") }}</span>
                                <p class="mb-0 text-dark small">{{ $resource->target_audience ?: translate('Not specified') }}</p>
                            </li>
                        </ul>
                    </div>
                </div>

                {{-- Products & Services Summary --}}
                @if(!empty($resource->products_services))
                <div class="card mb-4">
                    <div class="card-header">
                        <h4 class="card-title">{{ translate("Products & Services") }}</h4>
                    </div>
                    <div class="card-body">
                        <div class="d-flex flex-column gap-2">
                            @foreach($resource->products_services as $prod)
                                <div class="p-2 border rounded">
                                    <strong class="d-block text-dark">{{ $prod['name'] ?? translate('Item') }}</strong>
                                    <small class="text-muted">{{ $prod['benefit'] ?? '' }}</small>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                @endif
            </div>

            {{-- Right column: Interactive Content Engine & History --}}
            <div class="col-lg-7">
                {{-- Live Test Generator Card --}}
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="card-title mb-0">{{ translate("Deterministic Content Engine") }}</h4>
                            <small class="text-muted">{{ translate("Generates unique contextual content using templates and variable rotation") }}</small>
                        </div>
                        <button type="button" class="i-btn btn--primary btn--sm" id="btn_generate_preview">
                            <i class="ri-magic-line me-1"></i> {{ translate("Generate Sample") }}
                        </button>
                    </div>
                    <div class="card-body">
                        <div id="preview_box" class="p-3 bg-light rounded border">
                            <div class="mb-2">
                                <span class="badge bg-secondary text-white" id="preview_template_badge">template: intro_overview_v1</span>
                                <span class="badge bg-info text-white" id="preview_repeat_badge">{{ translate("New Content") }}</span>
                            </div>
                            <h6 class="fw-bold mb-2" id="preview_subject">{{ translate("Click 'Generate Sample' to test generation") }}</h6>
                            <div class="text-secondary small" id="preview_body">
                                <em>{{ translate("Content engine substitutes verified business facts, brand voice, and industry variables into anti-spam templates.") }}</em>
                            </div>
                            <hr class="my-2">
                            <small class="text-muted font-monospace d-block text-truncate" id="preview_fingerprint">
                                {{ translate("Fingerprint") }}: —
                            </small>
                        </div>
                    </div>
                </div>

                {{-- Generation History --}}
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">{{ translate("Recent Generated Dispatches & Fingerprints") }}</h4>
                    </div>
                    <div class="card-body px-0 pt-0">
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th scope="col">{{ translate("Subject") }}</th>
                                        <th scope="col">{{ translate("Template") }}</th>
                                        <th scope="col">{{ translate("Reuse Count") }}</th>
                                        <th scope="col">{{ translate("Last Used") }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($histories as $h)
                                    <tr>
                                        <td>
                                            <span class="fw-medium text-dark">{{ $h->subject }}</span>
                                            <br><small class="text-muted font-monospace">{{ substr($h->content_fingerprint, 0, 16) }}...</small>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark">{{ $h->template_id }}</span>
                                        </td>
                                        <td>
                                            <span class="i-badge {{ $h->reuse_count > 1 ? 'warning-soft' : 'success-soft' }} pill">
                                                {{ $h->reuse_count }}x
                                            </span>
                                        </td>
                                        <td>
                                            <small class="text-muted">{{ $h->last_used_at ? $h->last_used_at->diffForHumans() : $h->created_at->diffForHumans() }}</small>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-muted">
                                            {{ translate("No historical dispatches recorded for this resource yet.") }}
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

@push('script-include')
<script>
document.getElementById('btn_generate_preview')?.addEventListener('click', function() {
    const btn = this;
    btn.disabled = true;
    btn.innerHTML = '<i class="ri-loader-4-line ri-spin me-1"></i> {{ translate("Generating...") }}';

    fetch("{{ route('user.content-resource.preview', $resource->id) }}", {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json'
        },
        body: JSON.stringify({ record_usage: false })
    })
    .then(res => res.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="ri-magic-line me-1"></i> {{ translate("Generate Sample") }}';

        if (data.status && data.data) {
            const item = data.data;
            document.getElementById('preview_subject').innerText = item.subject;
            document.getElementById('preview_body').innerHTML = item.body;
            document.getElementById('preview_template_badge').innerText = 'template: ' + item.template_id;
            document.getElementById('preview_fingerprint').innerText = '{{ translate("Fingerprint") }}: ' + item.content_fingerprint;

            const repeatBadge = document.getElementById('preview_repeat_badge');
            if (item.is_repeated) {
                repeatBadge.className = 'badge bg-warning text-dark';
                repeatBadge.innerText = '{{ translate("Reused") }} (' + item.reuse_count + 'x)';
            } else {
                repeatBadge.className = 'badge bg-success text-white';
                repeatBadge.innerText = '{{ translate("Fresh Content") }}';
            }
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = '<i class="ri-magic-line me-1"></i> {{ translate("Generate Sample") }}';
        alert('{{ translate("Failed to generate content preview") }}');
    });
});
</script>
@endpush

@endsection
