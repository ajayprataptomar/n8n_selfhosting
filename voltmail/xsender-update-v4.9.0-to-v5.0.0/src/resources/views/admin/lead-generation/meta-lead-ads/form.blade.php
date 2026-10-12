@extends('admin.layouts.app')
@section('panel')
<main class="main-body">
    <div class="container-fluid px-0 main-content">
        <div class="page-header">
            <div class="page-header-left">
                <h2>{{ $title }}</h2>
            </div>
            <div class="page-header-right">
                <a href="{{ route('admin.lead-generation.meta-lead-ads.index') }}" class="i-btn btn--dark outline btn--md">
                    <i class="ri-arrow-left-line"></i> {{ translate('Back') }}
                </a>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <form action="{{ isset($subscription) ? route('admin.lead-generation.meta-lead-ads.update', $subscription->uid) : route('admin.lead-generation.meta-lead-ads.store') }}" method="POST">
                    @csrf
                    @if(isset($subscription)) @method('PUT') @endif

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">{{ translate('Subscription Name') }} <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required value="{{ old('name', $subscription->name ?? '') }}" placeholder="e.g. Real Estate Leads — Summer 2026">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">{{ translate('Page ID') }} <span class="text-danger">*</span></label>
                            <input type="text" name="page_id" class="form-control" required value="{{ old('page_id', $subscription->page_id ?? '') }}" placeholder="123456789012345">
                            <small class="text-muted">{{ translate('Facebook/Instagram page numeric ID.') }}</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">{{ translate('Page Name') }}</label>
                            <input type="text" name="page_name" class="form-control" value="{{ old('page_name', $subscription->page_name ?? '') }}">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">{{ translate('Form ID') }} <span class="text-danger">*</span></label>
                            <input type="text" name="form_id" class="form-control" required value="{{ old('form_id', $subscription->form_id ?? '') }}" placeholder="987654321098765">
                            <small class="text-muted">{{ translate('Numeric ID of the Lead Ads form (Meta Ads Manager → Forms Library).') }}</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">{{ translate('Form Name') }}</label>
                            <input type="text" name="form_name" class="form-control" value="{{ old('form_name', $subscription->form_name ?? '') }}">
                        </div>

                        <div class="col-12">
                            <label class="form-label">
                                {{ translate('Page Access Token') }}
                                @if(!isset($subscription))<span class="text-danger">*</span>@endif
                            </label>
                            <input type="text" name="page_access_token" class="form-control font-monospace" {{ !isset($subscription) ? 'required' : '' }} placeholder="{{ isset($subscription) ? translate('(leave blank to keep existing token)') : 'EAAB...' }}">
                            <small class="text-muted">{{ translate('Long-lived Page Access Token with leads_retrieval + pages_show_list + pages_read_engagement scopes. Encrypted at rest.') }}</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">{{ translate('Import into Contact Group') }}</label>
                            <select name="contact_group_id" class="form-select">
                                <option value="">{{ translate('— None —') }}</option>
                                @foreach($groups as $g)
                                    <option value="{{ $g->id }}" {{ (old('contact_group_id', $subscription->contact_group_id ?? null) == $g->id) ? 'selected' : '' }}>{{ $g->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">{{ translate('Enroll in Workflow (optional)') }}</label>
                            <select name="workflow_id" class="form-select">
                                <option value="">{{ translate('— None —') }}</option>
                                @foreach($workflows as $w)
                                    <option value="{{ $w->id }}" {{ (old('workflow_id', $subscription->workflow_id ?? null) == $w->id) ? 'selected' : '' }}>{{ $w->name }}</option>
                                @endforeach
                            </select>
                            <small class="text-muted">{{ translate('New leads will start this workflow automatically.') }}</small>
                        </div>

                        <div class="col-12">
                            <div class="form-check">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" value="1" class="form-check-input" id="isActive" {{ old('is_active', $subscription->is_active ?? true) ? 'checked' : '' }}>
                                <label for="isActive" class="form-check-label">{{ translate('Active') }}</label>
                            </div>
                        </div>

                        <div class="col-12">
                            <button class="i-btn btn--primary btn--md">
                                <i class="ri-save-line"></i> {{ translate('Save Subscription') }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>
@endsection
