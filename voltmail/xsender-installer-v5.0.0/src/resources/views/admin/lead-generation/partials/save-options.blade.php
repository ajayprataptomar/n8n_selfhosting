<h4 class="step-title">{{ translate('Where should we save the leads?') }}</h4>
<p class="step-subtitle">{{ translate('Choose how you want to handle the scraped leads') }}</p>

<div class="row justify-content-center g-3 mb-4">
    <div class="col-lg-4">
        <div class="save-option selected" data-save="review">
            <div class="d-flex align-items-center">
                <i class="ri-eye-line fs-3 text-primary me-3"></i>
                <div>
                    <strong class="d-block">{{ translate('Review First') }}</strong>
                    <small class="text-muted">{{ translate('View results before importing') }}</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="save-option" data-save="existing">
            <div class="d-flex align-items-center">
                <i class="ri-folder-line fs-3 text-primary me-3"></i>
                <div>
                    <strong class="d-block">{{ translate('Existing Group') }}</strong>
                    <small class="text-muted">{{ translate('Add to existing contact group') }}</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="save-option" data-save="new">
            <div class="d-flex align-items-center">
                <i class="ri-add-circle-line fs-3 text-primary me-3"></i>
                <div>
                    <strong class="d-block">{{ translate('New Group') }}</strong>
                    <small class="text-muted">{{ translate('Create new group automatically') }}</small>
                </div>
            </div>
        </div>
    </div>
</div>
<input type="hidden" name="save_option" id="saveOptionInput" value="review">

<!-- Existing Group Selection -->
<div class="row justify-content-center d-none" id="existingGroupSection">
    <div class="col-lg-6">
        <div class="form-inner">
            <label class="form-label">{{ translate('Select Contact Group') }}</label>
            <select class="form-select select2-search" name="existing_group_id">
                <option value="">{{ translate('Select Group') }}</option>
                @foreach($groups as $group)
                    <option value="{{ $group->id }}">{{ $group->name }}</option>
                @endforeach
            </select>
        </div>
    </div>
</div>

<!-- New Group Name -->
<div class="row justify-content-center d-none" id="newGroupSection">
    <div class="col-lg-6">
        <div class="form-inner">
            <label class="form-label">{{ translate('New Group Name') }}</label>
            <input type="text" class="form-control" name="new_group_name" id="newGroupName"
                   placeholder="{{ translate('e.g., NYC Restaurants, London Dentists') }}">
            <p class="form-element-note">{{ translate('Leave empty to auto-generate name based on search') }}</p>
        </div>
    </div>
</div>

<!-- Summary -->
<div class="row justify-content-center mt-4">
    <div class="col-lg-10">
        <div class="filter-card">
            <h6 class="mb-3"><i class="ri-file-list-line me-2"></i>{{ translate('Job Summary') }}</h6>
            <div class="row">
                <div class="col-md-6">
                    <p class="mb-2"><strong>{{ translate('Search:') }}</strong> <span id="summarySearch" class="text-muted">-</span></p>
                    <p class="mb-0"><strong>{{ translate('Location:') }}</strong> <span id="summaryLocation" class="text-muted">-</span></p>
                </div>
                <div class="col-md-6">
                    <p class="mb-2"><strong>{{ translate('Lead Type:') }}</strong> <span id="summaryLeadType" class="text-muted">{{ translate('All Contacts') }}</span></p>
                    <p class="mb-0"><strong>{{ translate('Max Results:') }}</strong> <span id="summaryMaxResults" class="text-muted">60</span></p>
                </div>
            </div>
        </div>
    </div>
</div>
