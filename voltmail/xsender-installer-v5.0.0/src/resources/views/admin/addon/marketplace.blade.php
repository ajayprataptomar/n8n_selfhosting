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
                            <li class="breadcrumb-item"><a href="{{ route('admin.addon.index') }}">{{ translate('Add-Ons') }}</a></li>
                            <li class="breadcrumb-item active">{{ translate('Marketplace') }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
            <div class="page-header-right d-flex gap-2">
                <a href="{{ route('admin.addon.marketplace', ['refresh' => 1]) }}" class="i-btn btn--info outline btn--md">
                    <i class="ri-refresh-line"></i> {{ translate('Refresh') }}
                </a>
                <a href="{{ route('admin.addon.index') }}" class="i-btn btn--dark outline btn--md">
                    <i class="ri-arrow-left-line"></i> {{ translate('Back') }}
                </a>
            </div>
        </div>

        @if(empty(site_settings('addon_marketplace_url')))
        <div class="alert alert-warning">
            <i class="ri-information-line"></i>
            {{ translate('Marketplace URL is not configured. Set') }} <code>addon_marketplace_url</code>
            {{ translate('in System Settings to point at your add-on catalog endpoint.') }}
        </div>
        @endif

        @if(!empty($listing['error']))
        <div class="alert alert-danger">
            <i class="ri-error-warning-line"></i>
            {{ translate('Failed to load marketplace listing:') }} {{ $listing['error'] }}
        </div>
        @endif

        <div class="row g-3">
            @forelse($listing['addons'] ?? [] as $addon)
            <div class="col-md-6 col-lg-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-start gap-3 mb-3">
                            @if(!empty($addon['icon']))
                                <img src="{{ $addon['icon'] }}" alt="" style="width:48px;height:48px;border-radius:8px;object-fit:cover;">
                            @else
                                <div style="width:48px;height:48px;border-radius:8px;background:#eef2ff;display:flex;align-items:center;justify-content:center;font-size:22px;color:#6366f1;">
                                    <i class="ri-puzzle-line"></i>
                                </div>
                            @endif
                            <div class="flex-grow-1">
                                <h5 class="mb-1">{{ $addon['name'] ?? $addon['slug'] }}</h5>
                                <small class="text-muted">{{ translate('Version') }} {{ $addon['version'] ?? '?' }}</small>
                            </div>
                            @if(!empty($addon['price']) && $addon['price'] !== '0')
                                <span class="badge bg-primary">${{ $addon['price'] }}</span>
                            @else
                                <span class="badge bg-success">{{ translate('Free') }}</span>
                            @endif
                        </div>

                        <p class="small text-muted" style="min-height:60px;">
                            {{ \Illuminate\Support\Str::limit($addon['description'] ?? '', 180) }}
                        </p>

                        @if(!empty($addon['tags']) && is_array($addon['tags']))
                        <div class="mb-2">
                            @foreach($addon['tags'] as $tag)
                                <span class="badge bg-light text-dark">{{ $tag }}</span>
                            @endforeach
                        </div>
                        @endif

                        @if(in_array($addon['slug'] ?? null, $installedSlugs, true))
                            <button class="btn btn-success btn-sm w-100" disabled>
                                <i class="ri-check-line"></i> {{ translate('Installed') }}
                            </button>
                        @else
                            <button class="btn btn-primary btn-sm w-100"
                                    onclick="installFromMarketplace(this, '{{ $addon['download_url'] ?? '' }}', '{{ $addon['slug'] ?? '' }}')">
                                <i class="ri-download-cloud-line"></i> {{ translate('Install') }}
                            </button>
                        @endif
                    </div>
                </div>
            </div>
            @empty
            <div class="col-12">
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="ri-store-line fs-1 text-muted mb-2 d-block"></i>
                        <h5>{{ translate('No add-ons available') }}</h5>
                        <p class="text-muted mb-0">{{ translate('Configure addon_marketplace_url in settings to see remote add-ons.') }}</p>
                    </div>
                </div>
            </div>
            @endforelse
        </div>
    </div>
</main>

<script>
function installFromMarketplace(btn, downloadUrl, slug) {
    if (!downloadUrl) {
        notify('error', '{{ translate("Add-on has no download URL") }}');
        return;
    }
    if (!confirm('{{ translate("Download and install this add-on?") }}')) return;

    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="ri-loader-4-line ri-spin"></i> {{ translate("Installing…") }}';

    const fd = new FormData();
    fd.append('download_url', downloadUrl);
    fd.append('slug', slug);
    fd.append('_token', document.querySelector('meta[name="csrf-token"]').content);

    fetch('{{ route("admin.addon.marketplace.install") }}', {
        method: 'POST',
        body: fd,
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    })
        .then(r => r.json())
        .then(data => {
            if (data.status) {
                notify('success', data.message);
                setTimeout(() => location.href = '{{ route("admin.addon.index") }}', 800);
            } else {
                btn.disabled = false;
                btn.innerHTML = original;
                notify('error', data.message || '{{ translate("Install failed") }}');
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = original;
            notify('error', err.message);
        });
}
</script>
@endsection
