@php
    $automationSettings = $plan->automation ?? (object)[
        'is_allowed' => false,
        'workflow_limit' => 0,
        'execution_limit' => 0,
    ];
@endphp

<div class="form-element">
    <div class="row gy-4">
        <div class="col-xxl-2 col-xl-3">
            <h5 class="form-element-title">
                <i class="ri-flow-chart me-2"></i>
                {{ translate("Workflow Automation") }}
            </h5>
        </div>
        <div class="col-xxl-8 col-xl-9">
            <div class="row gy-4 gx-xl-5">
                <!-- Enable Automation -->
                <div class="col-12">
                    <div class="form-inner">
                        <div class="form-inner-switch">
                            <div>
                                <label for="automation_enabled">
                                    <p class="fs-16 mb-2">{{ translate("Enable Workflow Automation") }}</p>
                                    <span class="text-muted">{{ translate("Allow users to create automated marketing workflows with triggers, conditions, and actions") }}</span>
                                </label>
                            </div>
                            <div class="switch-wrapper mb-1">
                                <input type="checkbox"
                                    class="switch-input automation-toggle"
                                    id="automation_enabled"
                                    name="automation_enabled"
                                    value="true"
                                    {{ ($automationSettings->is_allowed ?? false) ? 'checked' : '' }}>
                                <label for="automation_enabled" class="toggle">
                                    <span></span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Automation Limits -->
                <div class="col-md-6 automation-options {{ ($automationSettings->is_allowed ?? false) ? '' : 'd-none' }}">
                    <div class="form-inner">
                        <label for="automation_workflow_limit" class="form-label">
                            {{ translate("Workflow Limit") }}
                        </label>
                        <div class="input-group">
                            <input type="number"
                                min="-1"
                                id="automation_workflow_limit"
                                name="automation_workflow_limit"
                                class="form-control"
                                placeholder="{{ translate('Enter workflow limit') }}"
                                value="{{ $automationSettings->workflow_limit ?? 5 }}">
                            <span class="input-group-text fs-14">
                                {{ translate("Workflows") }}
                            </span>
                        </div>
                        <p class="form-element-note">
                            {{ translate("Set to -1 for unlimited. Maximum number of workflows a user can create.") }}
                        </p>
                    </div>
                </div>

                <div class="col-md-6 automation-options {{ ($automationSettings->is_allowed ?? false) ? '' : 'd-none' }}">
                    <div class="form-inner">
                        <label for="automation_execution_limit" class="form-label">
                            {{ translate("Monthly Execution Limit") }}
                        </label>
                        <div class="input-group">
                            <input type="number"
                                min="-1"
                                id="automation_execution_limit"
                                name="automation_execution_limit"
                                class="form-control"
                                placeholder="{{ translate('Enter execution limit') }}"
                                value="{{ $automationSettings->execution_limit ?? 1000 }}">
                            <span class="input-group-text fs-14">
                                {{ translate("Executions/Month") }}
                            </span>
                        </div>
                        <p class="form-element-note">
                            {{ translate("Set to -1 for unlimited. Maximum workflow executions per month.") }}
                        </p>
                    </div>
                </div>

                <!-- Info Box -->
                <div class="col-12 automation-options {{ ($automationSettings->is_allowed ?? false) ? '' : 'd-none' }}">
                    <div class="alert alert-soft-info">
                        <div class="d-flex gap-3">
                            <i class="ri-information-line fs-4"></i>
                            <div>
                                <h6 class="mb-1">{{ translate("Workflow Automation Features") }}</h6>
                                <ul class="mb-0 ps-3">
                                    <li>{{ translate("Visual drag-and-drop workflow builder") }}</li>
                                    <li>{{ translate("Multiple trigger types (New Contact, Schedule, Birthday, Webhook)") }}</li>
                                    <li>{{ translate("Actions: Send SMS, Email, WhatsApp messages") }}</li>
                                    <li>{{ translate("Conditions and branching logic") }}</li>
                                    <li>{{ translate("Wait/delay nodes for timed sequences") }}</li>
                                    <li>{{ translate("Pre-built workflow templates") }}</li>
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

    // Toggle automation options visibility
    $('.automation-toggle').on('change', function() {
        if ($(this).is(':checked')) {
            $('.automation-options').removeClass('d-none');
        } else {
            $('.automation-options').addClass('d-none');
        }
    });
})(jQuery);
</script>
@endpush
