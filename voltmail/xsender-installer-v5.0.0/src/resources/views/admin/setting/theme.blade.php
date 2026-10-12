@push("style-include")
<link rel="stylesheet" href="{{asset('assets/theme/admin/css/theme-settings.css')}}" />
@endpush

@php
    $availableThemes = getAvailableFrontendThemes();
    $activeTheme = getActiveFrontendTheme();
@endphp

@extends('admin.layouts.app')
@section("panel")
<main class="main-body">
    <div class="container-fluid px-0 main-content">
        <div class="page-header">
            <div class="page-header-left">
                <h2>{{ $title }}</h2>
                <div class="breadcrumb-wrapper">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item">
                                <a href="{{ route('admin.dashboard') }}">{{ translate('Dashboard') }}</a>
                            </li>
                            <li class="breadcrumb-item active" aria-current="page">{{ $title }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>

        <div class="pill-tab mb-4">
            <ul class="nav" role="tablist">
                <li class="nav-item" role="presentation">
                    <a class="nav-link active" data-bs-toggle="tab" href="#frontend" role="tab" aria-selected="true">
                        <i class="ri-palette-line"></i>
                        {{ translate('Frontend Themes') }}
                    </a>
                </li>
            </ul>
        </div>

        <div class="tab-content">
            <div class="tab-pane active fade show" id="frontend" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <div class="card-header-left">
                            <h4 class="card-title">
                                <i class="ri-palette-line me-2"></i>
                                {{ translate('Available Themes') }}
                            </h4>
                        </div>
                        <div class="card-header-right">
                            <span class="badge theme-gradiant-badge p-2">
                                {{ translate('Current') }}: {{ Arr::get($availableThemes, "$activeTheme.name", 'Default') }}
                            </span>
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="row g-4">
                            @forelse($availableThemes as $slug => $theme)
                            @php
                            $screenshots = getFrontendThemeScreenshots($slug);
                            @endphp
                            <div class="col-xxl-3 col-xl-4 col-md-6">
                                <div class="theme-card card h-100 {{ isActiveFrontendTheme($slug) ? 'active' : '' }}" 
                                     role="button"
                                     tabindex="0"
                                     data-bs-toggle="modal" 
                                     data-bs-target="#themeModal"
                                     data-theme="{{ $slug }}"
                                     data-name="{{ Arr::get($theme, 'name') }}"
                                     data-description="{{ Arr::get($theme, 'description') }}"
                                     data-version="{{ Arr::get($theme, 'version', 'N/A') }}"
                                     data-features="{{ json_encode(Arr::get($theme, 'features', [])) }}"
                                     data-screenshots="{{ json_encode($screenshots) }}">
                                    
                                    @if(isActiveFrontendTheme($slug))
                                    <div class="position-absolute top-0 end-0 m-2 active-badge text-white px-2 py-1 rounded-pill small fw-bold" style="z-index: 10; font-size: 0.7rem;">
                                        <i class="ri-check-line me-1"></i>{{ translate('Active') }}
                                    </div>
                                    @endif
                                    
                                    <div class="theme-card-image">
                                        <div class="theme-preview-overlay">
                                            <button class="btn btn-outline-light btn-sm rounded-pill" type="button">
                                                <i class="ri-eye-line me-1"></i>{{ translate('Preview') }}
                                            </button>
                                        </div>
                                        <img src="{{ getFrontendThemeThumbnail($slug) }}" 
                                             class="w-100 h-100 object-fit-cover" 
                                             alt="{{ Arr::get($theme, 'name') }}">
                                    </div>
                                    
                                    <div class="card-body p-3">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h6 class="fw-bold mb-0 text-truncate me-2">{{ Arr::get($theme, 'name') }}</h6>
                                            @if(Arr::has($theme, 'version'))
                                            <small class="i-badge dot success-soft pill">v{{ Arr::get($theme, 'version') }}</small>
                                            @endif
                                        </div>
                                        <p class="text-muted small mb-3 lh-sm" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                            {{ Arr::get($theme, 'description') }}
                                        </p>
                                        <div class="d-flex align-items-center justify-content-between text-muted small">
                                            <div class="d-flex align-items-center">
                                                <i class="ri-star-line me-1"></i>
                                                <span>{{ count(Arr::get($theme, 'features', [])) }} {{ translate('features') }}</span>
                                            </div>
                                            <div class="d-flex align-items-center">
                                                <i class="ri-image-line me-1"></i>
                                                <span>{{ count(Arr::get($theme, 'screenshots', [])) }} {{ translate('previews') }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @empty
                            <div class="col-12">
                                <div class="text-center py-5">
                                    <i class="ri-palette-line display-4 text-muted mb-3"></i>
                                    <h5 class="text-muted">{{ translate('No themes available') }}</h5>
                                    <p class="text-muted">{{ translate('Please add themes to the configuration file') }}</p>
                                </div>
                            </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
@endsection

@section("modal")
<div class="modal fade theme-modal" id="themeModal" tabindex="-1" aria-labelledby="themeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('admin.system.setting.store') }}" method="POST">
                @csrf
                <input type="hidden" name="site_settings[{{ \App\Enums\SettingKey::FRONTEND_ACTIVE_THEME->value }}]" id="selectedTheme" value="">
                
                <div class="modal-header">
                    <div class="flex-grow-1">
                        <h5 class="modal-title mb-1 fw-bold" id="modalTitle">{{ translate('Theme Preview') }}</h5>
                        <p class="mb-0 text-muted small" id="modalDescription"></p>
                    </div>
                    <button type="button" class="icon-btn btn-ghost btn-sm danger-soft circle modal-closer" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ri-close-large-line"></i>
                    </button>
                </div>
                
                <div class="modal-body">
                    <div>
                        <div class="preview-main-image">
                            <img id="mainPreviewImage" src="/placeholder.svg" 
                                    class="w-100 h-auto object-fit-cover" 
                                    alt="Theme Preview">
                        </div>
                        
                        <div class="thumbnail-carousel-wrapper my-4">
                            <div class="screenshots-header">
                                <h6>{{ translate('Screenshots') }}</h6>
                                <span class="badge theme-image-counter rounded-pill px-2 py-1 small" id="imageCounter">1/1</span>
                            </div>

                            <div class="thumbnail-carousel-container">
                                <div class="thumbnail-carousel position-relative">
                                    <div class="thumbnail-carousel" id="thumbnailCarousel"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="theme-info-section">
                            <h6 class="fw-bold mb-3">{{ translate('Theme Features') }}</h6>
                            <div id="featuresList" class="d-flex flex-wrap"></div>
                            
                            <div class="row mt-4">
                                <div class="col-md-6">
                                    <h6 class="fw-bold mb-2">{{ translate('Theme Information') }}</h6>
                                    <div class="small text-muted" id="themeInfo"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="i-btn btn--danger outline btn--md" data-bs-dismiss="modal">
                        {{ translate('Close') }}
                    </button>
                    <button type="submit" class="i-btn btn--primary btn--md" id="applyThemeBtn">
                        {{ translate('Apply Theme') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push("script-push")
    <script>

        const activeTheme = '{{ $activeTheme }}';
        let currentIndex = 0;
        let currentThemeData = null;
        let carouselOffset = 0;
        const thumbnailsPerView = 3;

        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('themeModal');
            
            modal.addEventListener('show.bs.modal', function() {
                document.activeElement?.blur();
            });
            
            modal.addEventListener('hidden.bs.modal', function() {
                document.activeElement?.blur();
                currentIndex = 0;
                currentThemeData = null;
                carouselOffset = 0;
            });
            
            document.querySelectorAll('[data-theme]').forEach(card => {
                card.addEventListener('click', handleThemeCardClick);
                card.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        handleThemeCardClick.call(this);
                    }
                });
            });
            
            document.getElementById('prevBtn').addEventListener('click', () => navigateCarousel('prev'));
            document.getElementById('nextBtn').addEventListener('click', () => navigateCarousel('next'));
        });
    </script>
    <script src="{{asset('assets/theme/admin/js/theme-settings.js')}}"></script>

@endpush
