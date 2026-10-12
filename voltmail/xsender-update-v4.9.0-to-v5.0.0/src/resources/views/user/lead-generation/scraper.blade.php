@push("style-include")
<link rel="stylesheet" href="{{ asset('assets/theme/global/css/select2.min.css')}}">
<style>
/* Step Wizard */
.step-wizard { display: flex; justify-content: center; margin-bottom: 2rem; gap: 0.5rem; }
.step-item { display: flex; align-items: center; }
.step-item .step-line { width: 60px; height: 2px; background: var(--border-color, #e0e0e0); margin: 0 8px; }
.step-item.completed .step-line { background: var(--color-primary); }
.step-circle { width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: var(--bg-light, #f3f4f6); color: var(--text-muted, #6b7280); font-weight: 600; font-size: 1rem; transition: all 0.3s; border: 2px solid transparent; }
.step-item.active .step-circle { background: var(--color-primary); color: #fff; border-color: var(--color-primary); box-shadow: 0 0 0 4px var(--color-primary-light); }
.step-item.completed .step-circle { background: var(--success-color, #10b981); color: #fff; border-color: var(--success-color, #10b981); }
.step-label { font-size: 0.75rem; color: var(--text-muted, #6b7280); margin-top: 0.5rem; text-align: center; max-width: 80px; }
.step-item.active .step-label { color: var(--color-primary); font-weight: 600; }
.step-item.completed .step-label { color: var(--success-color, #10b981); }

/* Step Content */
.step-content { display: none; animation: fadeIn 0.3s ease; }
.step-content.active { display: block; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

.step-title { font-size: 1.25rem; font-weight: 600; text-align: center; margin-bottom: 0.5rem; color: var(--text-color, #1f2937); }
.step-subtitle { font-size: 0.9375rem; color: var(--text-muted, #6b7280); text-align: center; margin-bottom: 1.5rem; }

/* Category Cards */
.category-card { border: 2px solid var(--border-color, #e0e0e0); border-radius: 12px; padding: 1.25rem 1rem; cursor: pointer; transition: all 0.2s; text-align: center; background: var(--card-bg, #fff); }
.category-card:hover { border-color: var(--color-primary); background: var(--color-primary-light); transform: translateY(-2px); }
.category-card.selected { border-color: var(--color-primary); background: var(--color-primary-light); }
.category-card i { font-size: 1.75rem; margin-bottom: 0.5rem; display: block; color: var(--color-primary); }
.category-card strong { font-size: 0.875rem; color: var(--text-color, #1f2937); }

/* Lead Type Cards */
.lead-type-card { border: 2px solid var(--border-color, #e0e0e0); border-radius: 12px; padding: 1.25rem; cursor: pointer; transition: all 0.2s; background: var(--card-bg, #fff); }
.lead-type-card:hover { border-color: var(--color-primary); }
.lead-type-card.selected { border-color: var(--color-primary); background: var(--color-primary-light); }
.lead-type-card i { font-size: 1.5rem; color: var(--color-primary); }

/* Save Option Cards */
.save-option { border: 2px solid var(--border-color, #e0e0e0); border-radius: 12px; padding: 1.25rem; cursor: pointer; transition: all 0.2s; background: var(--card-bg, #fff); }
.save-option:hover { border-color: var(--color-primary); }
.save-option.selected { border-color: var(--color-primary); background: var(--color-primary-light); }

/* Filter Card */
.filter-card { background: var(--bg-light, #f8fafc); border-radius: 12px; padding: 1.5rem; border: 1px solid var(--border-color, #e9ecef); }
.filter-card h6 { font-size: 0.9375rem; font-weight: 600; color: var(--text-color, #1f2937); }

/* URL Input Area */
.url-input-area { background: var(--bg-light, #f8fafc); border-radius: 12px; padding: 1.5rem; border: 1px solid var(--border-color, #e9ecef); }
.url-input-area textarea { font-family: 'Monaco', 'Menlo', 'Consolas', monospace; font-size: 0.875rem; line-height: 1.6; }
.url-tips { display: flex; flex-wrap: wrap; gap: 1rem; margin-top: 1rem; }
.url-tip { display: flex; align-items: center; gap: 0.5rem; font-size: 0.8125rem; color: var(--text-muted, #6b7280); }
.url-tip i { color: var(--color-primary); }

/* Quota Alert */
.quota-alert { background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(99, 102, 241, 0.1) 100%); border: 1px solid rgba(59, 130, 246, 0.2); border-radius: 12px; padding: 1rem 1.25rem; }
.quota-alert i { color: #3b82f6; }

/* Dark Mode */
[data-theme="dark"] .step-circle { background: var(--bg-light-dark, #374151); color: var(--text-muted-dark, #9ca3af); }
[data-theme="dark"] .step-item .step-line { background: var(--border-color-dark, #4b5563); }
[data-theme="dark"] .category-card, [data-theme="dark"] .lead-type-card, [data-theme="dark"] .save-option { background: var(--card-bg-dark, #1f2937); border-color: var(--border-color-dark, #374151); }
[data-theme="dark"] .filter-card, [data-theme="dark"] .url-input-area { background: var(--bg-light-dark, #374151); border-color: var(--border-color-dark, #4b5563); }
[data-theme="dark"] .quota-alert { background: rgba(59, 130, 246, 0.15); border-color: rgba(59, 130, 246, 0.3); }
</style>
@endpush

@extends('user.layouts.app')
@section('panel')

<main class="main-body">
    <div class="container-fluid px-0 main-content">
        <div class="page-header">
            <div class="page-header-left">
                <h2>{{ $title }}</h2>
                <div class="breadcrumb-wrapper">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            @foreach($breadcrumbs as $breadcrumb)
                                @if(isset($breadcrumb['url']))
                                    <li class="breadcrumb-item">
                                        <a href="{{ $breadcrumb['url'] }}">{{ $breadcrumb['name'] }}</a>
                                    </li>
                                @else
                                    <li class="breadcrumb-item active" aria-current="page">{{ $breadcrumb['name'] }}</li>
                                @endif
                            @endforeach
                        </ol>
                    </nav>
                </div>
            </div>
        </div>

        <!-- Quota Info -->
        <div class="quota-alert d-flex align-items-center mb-4">
            <i class="ri-information-line fs-4 me-3"></i>
            <div>
                <strong>{{ translate('Available Quota:') }}</strong>
                {{ $settings->remaining_daily }} {{ translate('leads today') }} |
                {{ $settings->remaining_monthly }} {{ translate('leads this month') }}
            </div>
        </div>

        <div class="card">
            <div class="card-body p-4">
                @if($typeEnum->value == 'google_maps')
                    <!-- Google Maps: 4 Steps -->
                    <div class="step-wizard" id="stepWizard">
                        <div class="step-item active" data-step="1">
                            <div class="d-flex flex-column align-items-center">
                                <div class="step-circle">1</div>
                                <span class="step-label">{{ translate('Search') }}</span>
                            </div>
                            <div class="step-line"></div>
                        </div>
                        <div class="step-item" data-step="2">
                            <div class="d-flex flex-column align-items-center">
                                <div class="step-circle">2</div>
                                <span class="step-label">{{ translate('Location') }}</span>
                            </div>
                            <div class="step-line"></div>
                        </div>
                        <div class="step-item" data-step="3">
                            <div class="d-flex flex-column align-items-center">
                                <div class="step-circle">3</div>
                                <span class="step-label">{{ translate('Filters') }}</span>
                            </div>
                            <div class="step-line"></div>
                        </div>
                        <div class="step-item" data-step="4">
                            <div class="d-flex flex-column align-items-center">
                                <div class="step-circle">4</div>
                                <span class="step-label">{{ translate('Save') }}</span>
                            </div>
                        </div>
                    </div>
                @else
                    <!-- Website Scraper: 3 Steps -->
                    <div class="step-wizard" id="stepWizard">
                        <div class="step-item active" data-step="1">
                            <div class="d-flex flex-column align-items-center">
                                <div class="step-circle">1</div>
                                <span class="step-label">{{ translate('URLs') }}</span>
                            </div>
                            <div class="step-line"></div>
                        </div>
                        <div class="step-item" data-step="2">
                            <div class="d-flex flex-column align-items-center">
                                <div class="step-circle">2</div>
                                <span class="step-label">{{ translate('Options') }}</span>
                            </div>
                            <div class="step-line"></div>
                        </div>
                        <div class="step-item" data-step="3">
                            <div class="d-flex flex-column align-items-center">
                                <div class="step-circle">3</div>
                                <span class="step-label">{{ translate('Save') }}</span>
                            </div>
                        </div>
                    </div>
                @endif

                <form id="scraperForm">
                    @csrf
                    <input type="hidden" name="type" value="{{ $typeEnum->value }}">

                    @if($typeEnum->value == 'google_maps')
                        {{-- GOOGLE MAPS STEPS --}}

                        <!-- Step 1: Industry & Search -->
                        <div class="step-content active" data-step="1">
                            <h4 class="step-title">{{ translate('What type of business are you looking for?') }}</h4>
                            <p class="step-subtitle">{{ translate('Select a category or enter custom search keywords') }}</p>

                            <!-- Industry Categories -->
                            <div class="row g-3 mb-4">
                                @php
                                    $categories = [
                                        'restaurants' => ['label' => 'Restaurants', 'icon' => 'ri-restaurant-line', 'keyword' => 'restaurant'],
                                        'retail' => ['label' => 'Retail Shops', 'icon' => 'ri-store-line', 'keyword' => 'shop'],
                                        'health' => ['label' => 'Healthcare', 'icon' => 'ri-hospital-line', 'keyword' => 'doctor clinic'],
                                        'beauty' => ['label' => 'Beauty & Spa', 'icon' => 'ri-scissors-cut-line', 'keyword' => 'salon spa'],
                                        'automotive' => ['label' => 'Automotive', 'icon' => 'ri-car-line', 'keyword' => 'car dealer mechanic'],
                                        'real_estate' => ['label' => 'Real Estate', 'icon' => 'ri-building-line', 'keyword' => 'real estate agent'],
                                        'legal' => ['label' => 'Legal', 'icon' => 'ri-scales-line', 'keyword' => 'lawyer attorney'],
                                        'finance' => ['label' => 'Finance', 'icon' => 'ri-bank-line', 'keyword' => 'accountant financial'],
                                        'hotels' => ['label' => 'Hotels', 'icon' => 'ri-hotel-line', 'keyword' => 'hotel resort'],
                                        'fitness' => ['label' => 'Fitness', 'icon' => 'ri-run-line', 'keyword' => 'gym fitness'],
                                        'education' => ['label' => 'Education', 'icon' => 'ri-graduation-cap-line', 'keyword' => 'school tutor'],
                                        'other' => ['label' => 'Other', 'icon' => 'ri-more-line', 'keyword' => ''],
                                    ];
                                @endphp
                                @foreach($categories as $key => $cat)
                                    <div class="col-xl-2 col-lg-3 col-md-4 col-6">
                                        <div class="category-card" data-keyword="{{ $cat['keyword'] }}">
                                            <i class="{{ $cat['icon'] }}"></i>
                                            <strong>{{ translate($cat['label']) }}</strong>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <!-- Custom Search -->
                            <div class="row justify-content-center">
                                <div class="col-lg-8">
                                    <div class="form-inner">
                                        <label class="form-label">{{ translate('Or enter custom search keywords') }}</label>
                                        <input type="text" class="form-control form-control-lg" name="query" id="searchQuery"
                                               placeholder="{{ translate('e.g., coffee shops, dentists, web developers') }}">
                                        <p class="form-element-note">{{ translate('Separate multiple keywords with commas') }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Step 2: Location -->
                        <div class="step-content" data-step="2">
                            <h4 class="step-title">{{ translate('Where should we search?') }}</h4>
                            <p class="step-subtitle">{{ translate('Select the country and city to find local businesses') }}</p>

                            <div class="row justify-content-center g-4">
                                <div class="col-lg-5">
                                    <div class="form-inner">
                                        <label class="form-label">{{ translate('Country') }} <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control form-control-lg" name="country" id="countryInput"
                                               list="countryList" autocomplete="off"
                                               placeholder="{{ translate('Type or select a country') }}">
                                        <datalist id="countryList">
                                            @foreach(config('lead-generation.countries') as $code => $name)
                                                <option value="{{ $name }}">
                                            @endforeach
                                        </datalist>
                                        <small class="form-text text-muted">{{ translate('Select from list or type any country name') }}</small>
                                    </div>
                                </div>
                                <div class="col-lg-5">
                                    <div class="form-inner">
                                        <label class="form-label">{{ translate('City / Region') }} <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control form-control-lg" name="city" id="cityInput"
                                               placeholder="{{ translate('e.g., New York, Los Angeles, London') }}">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Step 3: Filters -->
                        <div class="step-content" data-step="3">
                            <h4 class="step-title">{{ translate('Refine your search') }}</h4>
                            <p class="step-subtitle">{{ translate('Choose the type of contacts and filter options') }}</p>

                            <div class="row justify-content-center g-3 mb-4">
                                <div class="col-lg-3 col-md-6">
                                    <div class="lead-type-card text-center selected" data-type="all">
                                        <i class="ri-group-line d-block mb-2"></i>
                                        <strong class="d-block mb-1">{{ translate('All Contacts') }}</strong>
                                        <small class="text-muted">{{ translate('Email & Phone') }}</small>
                                    </div>
                                </div>
                                <div class="col-lg-3 col-md-6">
                                    <div class="lead-type-card text-center" data-type="email">
                                        <i class="ri-mail-line d-block mb-2"></i>
                                        <strong class="d-block mb-1">{{ translate('Email Only') }}</strong>
                                        <small class="text-muted">{{ translate('Must have email') }}</small>
                                    </div>
                                </div>
                                <div class="col-lg-3 col-md-6">
                                    <div class="lead-type-card text-center" data-type="phone">
                                        <i class="ri-phone-line d-block mb-2"></i>
                                        <strong class="d-block mb-1">{{ translate('Phone Only') }}</strong>
                                        <small class="text-muted">{{ translate('Must have phone') }}</small>
                                    </div>
                                </div>
                            </div>
                            <input type="hidden" name="lead_type" id="leadTypeInput" value="all">

                            <div class="row justify-content-center">
                                <div class="col-lg-10">
                                    <div class="filter-card">
                                        <h6 class="mb-3"><i class="ri-filter-line me-2"></i>{{ translate('Additional Filters') }}</h6>
                                        <div class="row g-3">
                                            <div class="col-lg-6">
                                                <label class="form-label">{{ translate('Maximum Results') }}</label>
                                                <select class="form-select" name="max_results">
                                                    <option value="20">20 {{ translate('leads') }}</option>
                                                    <option value="40">40 {{ translate('leads') }}</option>
                                                    <option value="60" selected>60 {{ translate('leads') }}</option>
                                                </select>
                                            </div>
                                            <div class="col-lg-6">
                                                <label class="form-label">{{ translate('Skip Duplicates') }}</label>
                                                <select class="form-select" name="skip_duplicates">
                                                    <option value="1" selected>{{ translate('Yes - Skip existing leads') }}</option>
                                                    <option value="0">{{ translate('No - Include all') }}</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Step 4: Save Options (Google Maps) -->
                        <div class="step-content" data-step="4">
                            <h4 class="step-title">{{ translate('Where to save your leads?') }}</h4>
                            <p class="step-subtitle">{{ translate('Choose how you want to handle the scraped leads') }}</p>

                            <div class="row justify-content-center g-3 mb-4">
                                <div class="col-lg-4">
                                    <div class="save-option selected" data-save="review">
                                        <div class="d-flex align-items-center">
                                            <i class="ri-eye-line fs-3 text-primary me-3"></i>
                                            <div>
                                                <strong class="d-block">{{ translate('Review First') }}</strong>
                                                <small class="text-muted">{{ translate('Preview before importing') }}</small>
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
                                                <small class="text-muted">{{ translate('Add to existing group') }}</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <input type="hidden" name="save_option" id="saveOptionInput" value="review">

                            <div class="row justify-content-center d-none" id="existingGroupSection">
                                <div class="col-lg-6">
                                    <label class="form-label">{{ translate('Select Group') }}</label>
                                    <select class="form-select select2-search" name="existing_group_id">
                                        <option value="">{{ translate('Select Group') }}</option>
                                        @foreach($groups as $group)
                                            <option value="{{ $group->id }}">{{ $group->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                    @else
                        {{-- WEBSITE SCRAPER STEPS --}}

                        <!-- Step 1: Enter URLs -->
                        <div class="step-content active" data-step="1">
                            <h4 class="step-title">{{ translate('Enter Website URLs to Scrape') }}</h4>
                            <p class="step-subtitle">{{ translate('We will extract emails and phone numbers from these websites') }}</p>

                            <div class="row justify-content-center">
                                <div class="col-lg-10">
                                    <div class="url-input-area">
                                        <div class="form-inner mb-0">
                                            <label class="form-label">{{ translate('Website URLs') }} <span class="text-danger">*</span></label>
                                            <textarea class="form-control" name="urls" rows="10"
                                                      placeholder="https://example.com&#10;https://another-site.com&#10;https://business-website.com/contact"></textarea>
                                        </div>
                                        <div class="url-tips">
                                            <span class="url-tip"><i class="ri-check-line"></i> {{ translate('One URL per line') }}</span>
                                            <span class="url-tip"><i class="ri-check-line"></i> {{ translate('Include https://') }}</span>
                                            <span class="url-tip"><i class="ri-check-line"></i> {{ translate('Maximum 50 URLs') }}</span>
                                        </div>

                                        <div class="alert alert-info mt-3 mb-0" style="font-size: 0.85rem;">
                                            <h6 class="alert-heading mb-2"><i class="ri-lightbulb-line"></i> {{ translate('Tips for Best Results') }}</h6>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <p class="mb-1"><strong>{{ translate('Best URLs to scrape') }}:</strong></p>
                                                    <ul class="mb-2 ps-3">
                                                        <li>{{ translate('Contact pages') }}: <code>/contact</code>, <code>/contact-us</code></li>
                                                        <li>{{ translate('About pages') }}: <code>/about</code>, <code>/about-us</code>, <code>/team</code></li>
                                                        <li>{{ translate('Footer/legal pages') }}: <code>/privacy</code>, <code>/imprint</code></li>
                                                    </ul>
                                                </div>
                                                <div class="col-md-6">
                                                    <p class="mb-1"><strong>{{ translate('URLs that may not work') }}:</strong></p>
                                                    <ul class="mb-2 ps-3">
                                                        <li>{{ translate('JavaScript-heavy sites (React/Vue apps)') }}</li>
                                                        <li>{{ translate('Sites with contact forms only (no visible email)') }}</li>
                                                        <li>{{ translate('Cloudflare protected or CAPTCHA sites') }}</li>
                                                    </ul>
                                                </div>
                                            </div>
                                            <p class="mb-0"><i class="ri-arrow-right-line"></i> <strong>{{ translate('Pro Tip') }}:</strong> {{ translate('Enter the direct contact/about page URL (e.g., https://example.com/contact) for best results.') }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Step 2: Lead Type & Options (Website) -->
                        <div class="step-content" data-step="2">
                            <h4 class="step-title">{{ translate('What type of contacts do you need?') }}</h4>
                            <p class="step-subtitle">{{ translate('Choose the contact information you want to extract') }}</p>

                            <div class="row justify-content-center g-3 mb-4">
                                <div class="col-lg-3 col-md-6">
                                    <div class="lead-type-card text-center selected" data-type="all">
                                        <i class="ri-group-line d-block mb-2"></i>
                                        <strong class="d-block mb-1">{{ translate('All Contacts') }}</strong>
                                        <small class="text-muted">{{ translate('Email & Phone') }}</small>
                                    </div>
                                </div>
                                <div class="col-lg-3 col-md-6">
                                    <div class="lead-type-card text-center" data-type="email">
                                        <i class="ri-mail-line d-block mb-2"></i>
                                        <strong class="d-block mb-1">{{ translate('Email Only') }}</strong>
                                        <small class="text-muted">{{ translate('Must have email') }}</small>
                                    </div>
                                </div>
                                <div class="col-lg-3 col-md-6">
                                    <div class="lead-type-card text-center" data-type="phone">
                                        <i class="ri-phone-line d-block mb-2"></i>
                                        <strong class="d-block mb-1">{{ translate('Phone Only') }}</strong>
                                        <small class="text-muted">{{ translate('Must have phone') }}</small>
                                    </div>
                                </div>
                            </div>
                            <input type="hidden" name="lead_type" id="leadTypeInput" value="all">

                            <div class="row justify-content-center">
                                <div class="col-lg-8">
                                    <div class="filter-card">
                                        <h6 class="mb-3"><i class="ri-shield-check-line me-2"></i>{{ translate('Quality Preferences') }}</h6>
                                        <div class="row g-3">
                                            <div class="col-lg-6">
                                                <label class="form-label">{{ translate('Skip Duplicates') }}</label>
                                                <select class="form-select" name="skip_duplicates">
                                                    <option value="1" selected>{{ translate('Yes - Skip existing leads') }}</option>
                                                    <option value="0">{{ translate('No - Include all') }}</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Step 3: Save Options (Website) -->
                        <div class="step-content" data-step="3">
                            <h4 class="step-title">{{ translate('Where to save your leads?') }}</h4>
                            <p class="step-subtitle">{{ translate('Choose how you want to handle the scraped leads') }}</p>

                            <div class="row justify-content-center g-3 mb-4">
                                <div class="col-lg-4">
                                    <div class="save-option selected" data-save="review">
                                        <div class="d-flex align-items-center">
                                            <i class="ri-eye-line fs-3 text-primary me-3"></i>
                                            <div>
                                                <strong class="d-block">{{ translate('Review First') }}</strong>
                                                <small class="text-muted">{{ translate('Preview before importing') }}</small>
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
                                                <small class="text-muted">{{ translate('Add to existing group') }}</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <input type="hidden" name="save_option" id="saveOptionInput" value="review">

                            <div class="row justify-content-center d-none" id="existingGroupSection">
                                <div class="col-lg-6">
                                    <label class="form-label">{{ translate('Select Group') }}</label>
                                    <select class="form-select select2-search" name="existing_group_id">
                                        <option value="">{{ translate('Select Group') }}</option>
                                        @foreach($groups as $group)
                                            <option value="{{ $group->id }}">{{ $group->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                    @endif

                    <!-- Navigation Buttons -->
                    <div class="d-flex justify-content-between mt-4 pt-3 border-top">
                        <button type="button" class="i-btn btn--dark outline btn--md" id="prevBtn" disabled>
                            <i class="ri-arrow-left-line"></i> {{ translate('Back') }}
                        </button>
                        <div>
                            <a href="{{ route('user.lead-generation.index') }}" class="i-btn btn--danger outline btn--md me-2">
                                {{ translate('Cancel') }}
                            </a>
                            <button type="button" class="i-btn btn--primary btn--md" id="nextBtn">
                                {{ translate('Next') }} <i class="ri-arrow-right-line"></i>
                            </button>
                            <button type="submit" class="i-btn btn--success btn--md d-none" id="submitBtn">
                                <i class="ri-search-eye-line"></i> {{ translate('Start Scraping') }}
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Progress Section -->
                <div id="progressSection" class="mt-4 d-none">
                    <hr>
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary mb-3" style="width: 3rem; height: 3rem;"></div>
                        <h5 class="mb-2" id="statusText">{{ translate('Initializing...') }}</h5>
                        <div class="progress mb-3" style="height: 8px; max-width: 400px; margin: 0 auto;">
                            <div class="progress-bar" role="progressbar" style="width: 0%" id="progressBar"></div>
                        </div>
                        <p class="text-muted" id="countText">0 {{ translate('leads found') }}</p>
                    </div>
                </div>

                <!-- Results Section -->
                <div id="resultsSection" class="mt-4 d-none">
                    <hr>
                    <div class="text-center py-4">
                        <i class="ri-check-double-line text-success fs-1 d-block mb-3"></i>
                        <h5 class="mb-2 text-success">{{ translate('Scraping Complete!') }}</h5>
                        <p class="text-muted mb-4" id="resultsMessage"></p>
                        <a href="#" id="viewResultsBtn" class="i-btn btn--success btn--md">
                            <i class="ri-eye-line"></i> {{ translate('View Results') }}
                        </a>
                    </div>
                </div>

                <div id="errorSection" class="mt-4 d-none">
                    <div class="alert alert-danger text-center">
                        <i class="ri-error-warning-line fs-3 d-block mb-2"></i>
                        <span id="errorMessage"></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

@endsection

@push("script-include")
<script src="{{asset('assets/theme/global/js/select2.min.js')}}"></script>
@endpush

@push('script-push')
<script>
(function($) {
    "use strict";

    var currentStep = 1;
    var totalSteps = {{ $typeEnum->value == 'google_maps' ? 4 : 3 }};
    var checkInterval;
    var currentJobUid;
    var scraperType = '{{ $typeEnum->value }}';

    // Initialize Select2
    select2_search($('.select2-search').data('placeholder'));

    // Category selection
    $('.category-card').on('click', function() {
        $('.category-card').removeClass('selected');
        $(this).addClass('selected');
        var keyword = $(this).data('keyword');
        if (keyword) {
            $('#searchQuery').val(keyword);
        }
    });

    // Lead type selection
    $('.lead-type-card').on('click', function() {
        $('.lead-type-card').removeClass('selected');
        $(this).addClass('selected');
        $('#leadTypeInput').val($(this).data('type'));
    });

    // Save option selection
    $('.save-option').on('click', function() {
        $('.save-option').removeClass('selected');
        $(this).addClass('selected');
        var option = $(this).data('save');
        $('#saveOptionInput').val(option);
        $('#existingGroupSection').toggleClass('d-none', option !== 'existing');
    });

    // Navigation
    $('#nextBtn').on('click', function() {
        if (validateStep(currentStep)) {
            goToStep(currentStep + 1);
        }
    });

    $('#prevBtn').on('click', function() {
        goToStep(currentStep - 1);
    });

    function goToStep(step) {
        if (step < 1 || step > totalSteps) return;

        // Update step indicators
        $('.step-item').each(function() {
            var s = $(this).data('step');
            $(this).removeClass('active completed');
            if (s < step) {
                $(this).addClass('completed');
            } else if (s === step) {
                $(this).addClass('active');
            }
        });

        // Show/hide content
        $('.step-content').removeClass('active');
        $('.step-content[data-step="' + step + '"]').addClass('active');

        // Update buttons
        $('#prevBtn').prop('disabled', step === 1);
        if (step === totalSteps) {
            $('#nextBtn').addClass('d-none');
            $('#submitBtn').removeClass('d-none');
        } else {
            $('#nextBtn').removeClass('d-none');
            $('#submitBtn').addClass('d-none');
        }

        currentStep = step;
    }

    function validateStep(step) {
        if (scraperType === 'google_maps') {
            if (step === 1) {
                var query = $('#searchQuery').val().trim();
                if (!query && !$('.category-card.selected').length) {
                    notify('error', '{{ translate("Please select a category or enter search keywords") }}');
                    return false;
                }
                if (!query && $('.category-card.selected').length) {
                    query = $('.category-card.selected').data('keyword');
                    $('#searchQuery').val(query);
                }
            }
            if (step === 2) {
                var country = $('#countryInput').val();
                var city = $('#cityInput').val().trim();
                if (!country || !city) {
                    notify('error', '{{ translate("Please select country and enter city") }}');
                    return false;
                }
            }
        } else {
            // Website scraper
            if (step === 1) {
                var urls = $('textarea[name="urls"]').val().trim();
                if (!urls) {
                    notify('error', '{{ translate("Please enter at least one URL") }}');
                    return false;
                }
                var urlLines = urls.split('\n').filter(line => line.trim());
                if (urlLines.length > 50) {
                    notify('error', '{{ translate("Maximum 50 URLs allowed") }}');
                    return false;
                }
            }
        }
        return true;
    }

    // Form submission
    $('#scraperForm').on('submit', function(e) {
        e.preventDefault();

        // Build location field for Google Maps
        if (scraperType === 'google_maps') {
            var location = $('#cityInput').val() + ', ' + $('#countryInput').val();
            $('<input>').attr({type: 'hidden', name: 'location', value: location}).appendTo($(this));
        }

        // Hide form, show progress
        $('.step-content, #stepWizard, .border-top').addClass('d-none');
        $('#progressSection').removeClass('d-none');

        $.ajax({
            url: '{{ route("user.lead-generation.job.start") }}',
            method: 'POST',
            data: $(this).serialize(),
            success: function(response) {
                if (response.status) {
                    currentJobUid = response.data.job_id;
                    var pollCount = 0;
                    checkInterval = setInterval(function() {
                        pollCount++;
                        if (pollCount > 150) { // 5 min timeout
                            clearInterval(checkInterval);
                            showError('{{ translate("Scraping is taking too long. Please check if your cron/queue worker is running.") }}');
                            return;
                        }
                        checkJobStatus(currentJobUid);
                    }, 2000);
                } else {
                    showError(response.message);
                }
            },
            error: function(xhr) {
                showError(xhr.responseJSON?.message || '{{ translate("Failed to start") }}');
            }
        });
    });

    function checkJobStatus(jobId) {
        $.get('{{ route("user.lead-generation.job.status", "") }}/' + jobId, function(response) {
            if (response.status) {
                var data = response.data;
                $('#progressBar').css('width', data.progress + '%');
                $('#statusText').text(data.status_label);
                $('#countText').text(data.total_found + ' {{ translate("leads found") }}');

                if (data.status === 'completed') {
                    clearInterval(checkInterval);
                    showResults(jobId, data);
                } else if (data.status === 'failed' || data.status === 'cancelled') {
                    clearInterval(checkInterval);
                    showError(data.error_message || '{{ translate("Job failed") }}');
                } else if (data.status === 'pending' && pollCount > 15) {
                    $('#statusText').html('<span class="text-warning"><i class="ri-error-warning-line"></i> {{ translate("Job queued but not processing. Ensure cron/queue worker is running.") }}</span>');
                }
            }
        });
    }

    function showResults(jobId, data) {
        $('#progressSection').addClass('d-none');
        $('#resultsSection').removeClass('d-none');
        $('#resultsMessage').text('{{ translate("Found") }} ' + data.total_found + ' {{ translate("leads") }}');
        $('#viewResultsBtn').attr('href', '{{ route("user.lead-generation.results", "") }}/' + jobId);
    }

    function showError(message) {
        $('#progressSection').addClass('d-none');
        $('#errorSection').removeClass('d-none');
        $('#errorMessage').text(message);
    }

})(jQuery);
</script>
@endpush
