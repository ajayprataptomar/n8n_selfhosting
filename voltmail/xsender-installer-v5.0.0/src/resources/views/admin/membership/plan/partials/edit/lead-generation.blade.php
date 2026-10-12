@php
    $leadGenSettings = $plan->lead_generation ?? (object)[
        'is_allowed' => false,
        'daily_limit' => 0,
        'monthly_limit' => 0,
    ];
@endphp

<div class="form-element">
    <div class="row gy-4">
        <div class="col-xxl-2 col-xl-3">
            <h5 class="form-element-title">
                <i class="ri-search-eye-line me-2"></i>
                {{ translate("Lead Generation") }}
            </h5>
        </div>
        <div class="col-xxl-8 col-xl-9">
            <div class="row gy-4 gx-xl-5">
                <!-- Enable Lead Generation -->
                <div class="col-12">
                    <div class="form-inner">
                        <div class="form-inner-switch">
                            <div>
                                <label for="lead_generation_enabled">
                                    <p class="fs-16 mb-2">{{ translate("Enable Lead Generation") }}</p>
                                    <span class="text-muted">{{ translate("Allow users to access lead generation feature to scrape business contacts from Google Maps and websites") }}</span>
                                </label>
                            </div>
                            <div class="switch-wrapper mb-1">
                                <input type="checkbox"
                                    class="switch-input lead-generation-toggle"
                                    id="lead_generation_enabled"
                                    name="lead_generation_enabled"
                                    value="true"
                                    {{ ($leadGenSettings->is_allowed ?? false) ? 'checked' : '' }}>
                                <label for="lead_generation_enabled" class="toggle">
                                    <span></span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Lead Generation Limits -->
                <div class="col-md-6 lead-generation-options {{ ($leadGenSettings->is_allowed ?? false) ? '' : 'd-none' }}">
                    <div class="form-inner">
                        <label for="lead_daily_limit" class="form-label">
                            {{ translate("Daily Scrape Limit") }}
                        </label>
                        <div class="input-group">
                            <input type="number"
                                min="-1"
                                id="lead_daily_limit"
                                name="lead_daily_limit"
                                class="form-control"
                                placeholder="{{ translate('Enter daily limit') }}"
                                value="{{ $leadGenSettings->daily_limit ?? 100 }}">
                            <span class="input-group-text fs-14">
                                {{ translate("Leads/Day") }}
                            </span>
                        </div>
                        <p class="form-element-note">
                            {{ translate("Set to -1 for unlimited. Maximum leads a user can scrape per day.") }}
                        </p>
                    </div>
                </div>

                <div class="col-md-6 lead-generation-options {{ ($leadGenSettings->is_allowed ?? false) ? '' : 'd-none' }}">
                    <div class="form-inner">
                        <label for="lead_monthly_limit" class="form-label">
                            {{ translate("Monthly Scrape Limit") }}
                        </label>
                        <div class="input-group">
                            <input type="number"
                                min="-1"
                                id="lead_monthly_limit"
                                name="lead_monthly_limit"
                                class="form-control"
                                placeholder="{{ translate('Enter monthly limit') }}"
                                value="{{ $leadGenSettings->monthly_limit ?? 1000 }}">
                            <span class="input-group-text fs-14">
                                {{ translate("Leads/Month") }}
                            </span>
                        </div>
                        <p class="form-element-note">
                            {{ translate("Set to -1 for unlimited. Maximum leads a user can scrape per month.") }}
                        </p>
                    </div>
                </div>

                <!-- Info Box -->
                <div class="col-12 lead-generation-options {{ ($leadGenSettings->is_allowed ?? false) ? '' : 'd-none' }}">
                    <div class="alert alert-soft-info">
                        <div class="d-flex gap-3">
                            <i class="ri-information-line fs-4"></i>
                            <div>
                                <h6 class="mb-1">{{ translate("Lead Generation Features") }}</h6>
                                <ul class="mb-0 ps-3">
                                    <li>{{ translate("Google Maps business scraping") }}</li>
                                    <li>{{ translate("Website contact extraction") }}</li>
                                    <li>{{ translate("Export leads to CSV/Excel") }}</li>
                                    <li>{{ translate("Import leads to contact groups") }}</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('script-push')
<script>
(function($) {
    "use strict";

    // Toggle lead generation options visibility
    $('.lead-generation-toggle').on('change', function() {
        if ($(this).is(':checked')) {
            $('.lead-generation-options').removeClass('d-none');
        } else {
            $('.lead-generation-options').addClass('d-none');
        }
    });
})(jQuery);
</script>
@endpush
