@extends('user.layouts.app')
@section('panel')

<main class="main-body">
    <div class="container-fluid">
        <div class="page-header d-flex justify-content-between align-items-center mb-4">
            <div class="page-header-left">
                <h3 class="mb-1">{{ translate("New Business Content Resource") }}</h3>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="{{ route('user.dashboard') }}">{{ translate("Dashboard") }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('user.content-resource.index') }}">{{ translate("Content Resources") }}</a></li>
                        <li class="breadcrumb-item active">{{ translate("Create") }}</li>
                    </ol>
                </nav>
            </div>
            <div class="page-header-right">
                <a href="{{ route('user.content-resource.index') }}" class="i-btn btn--dark outline btn--md">
                    <i class="ri-arrow-left-line me-1"></i> {{ translate("Back to List") }}
                </a>
            </div>
        </div>

        <form action="{{ route('user.content-resource.store') }}" method="POST">
            @csrf
            <div class="row g-4">
                {{-- Left: Primary Business Identity --}}
                <div class="col-lg-8">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h4 class="card-title">{{ translate("Company & Business Profile") }}</h4>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label required">{{ translate("Resource / Profile Name") }}</label>
                                    <input type="text" name="name" class="form-control" value="{{ old('name') }}" placeholder="{{ translate('e.g. Primary SaaS Profile') }}" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label required">{{ translate("Company Name") }}</label>
                                    <input type="text" name="company_name" class="form-control" value="{{ old('company_name') }}" placeholder="{{ translate('e.g. Acme Cloud Corp') }}" required>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">{{ translate("Business Type") }}</label>
                                    <input type="text" name="business_type" class="form-control" value="{{ old('business_type') }}" placeholder="{{ translate('e.g. B2B SaaS, Agency, Managed Hosting') }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">{{ translate("Company Website") }}</label>
                                    <input type="url" name="website" class="form-control" value="{{ old('website') }}" placeholder="{{ translate('https://example.com') }}">
                                </div>

                                <div class="col-12">
                                    <label class="form-label">{{ translate("Company Description & Mission") }}</label>
                                    <textarea name="company_description" class="form-control" rows="3" placeholder="{{ translate('Describe what your business does and core value proposition...') }}">{{ old('company_description') }}</textarea>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">{{ translate("Industry Sector") }}</label>
                                    <input type="text" name="industry" class="form-control" value="{{ old('industry') }}" placeholder="{{ translate('e.g. Enterprise Cloud, Fintech, HealthTech') }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">{{ translate("Brand Voice & Tone") }}</label>
                                    <input type="text" name="brand_voice" class="form-control" value="{{ old('brand_voice') }}" placeholder="{{ translate('e.g. Professional & Authoritative, Friendly & Helpful') }}">
                                </div>

                                <div class="col-12">
                                    <label class="form-label">{{ translate("Target Audience & Ideal Customer Profile (ICP)") }}</label>
                                    <textarea name="target_audience" class="form-control" rows="2" placeholder="{{ translate('e.g. VP of Engineering, DevOps Leads, E-commerce Store Owners') }}">{{ old('target_audience') }}</textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Structured Knowledge: Products & FAQs --}}
                    <div class="card mb-4">
                        <div class="card-header">
                            <h4 class="card-title">{{ translate("Products, Services & Business Facts") }}</h4>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">{{ translate("Products / Services (JSON Array)") }}</label>
                                <textarea name="products_services" class="form-control font-monospace" rows="4" placeholder='[{"name": "Core Service", "benefit": "high performance delivery"}]'>{{ old('products_services') }}</textarea>
                                <small class="text-muted">{{ translate('Format: [{"name": "Product Name", "benefit": "Key value or outcome"}]') }}</small>
                            </div>

                            <div class="mb-0">
                                <label class="form-label">{{ translate("Approved Business Facts & FAQs (JSON Array)") }}</label>
                                <textarea name="faqs" class="form-control font-monospace" rows="4" placeholder='[{"question": "How quickly does integration take?", "answer": "Under 5 minutes with zero downtime."}]'>{{ old('faqs') }}</textarea>
                                <small class="text-muted">{{ translate('Format: [{"question": "...", "answer": "..."}]') }}</small>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Right: Status & Settings --}}
                <div class="col-lg-4">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h4 class="card-title">{{ translate("Settings & Activation") }}</h4>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">{{ translate("Status") }}</label>
                                <select name="status" class="form-select">
                                    <option value="active" {{ old('status', 'active') == 'active' ? 'selected' : '' }}>{{ translate("Active") }}</option>
                                    <option value="inactive" {{ old('status') == 'inactive' ? 'selected' : '' }}>{{ translate("Inactive") }}</option>
                                </select>
                            </div>

                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="is_default" value="1" id="is_default" {{ old('is_default') ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_default">
                                    {{ translate("Set as Default Resource") }}
                                </label>
                                <small class="text-muted d-block">{{ translate("Default resource will be automatically picked for warmup routines unless specified.") }}</small>
                            </div>

                            <button type="submit" class="i-btn btn--primary btn--md w-100">
                                <i class="ri-check-line me-1"></i> {{ translate("Save Business Resource") }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</main>

@endsection
