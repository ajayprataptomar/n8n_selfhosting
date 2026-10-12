<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Industry Categories
    |--------------------------------------------------------------------------
    |
    | Predefined industry categories for lead generation searches.
    | These are commonly searched business types on Google Maps.
    |
    */
    'categories' => [
        'restaurants' => [
            'label' => 'Restaurants & Food',
            'keywords' => ['restaurant', 'cafe', 'bakery', 'pizza', 'fast food', 'bar', 'pub', 'catering'],
        ],
        'retail' => [
            'label' => 'Retail & Shopping',
            'keywords' => ['shop', 'store', 'boutique', 'mall', 'supermarket', 'grocery'],
        ],
        'health' => [
            'label' => 'Healthcare',
            'keywords' => ['doctor', 'dentist', 'hospital', 'clinic', 'pharmacy', 'medical', 'therapist'],
        ],
        'beauty' => [
            'label' => 'Beauty & Wellness',
            'keywords' => ['salon', 'spa', 'barber', 'beauty', 'massage', 'nail', 'cosmetic'],
        ],
        'automotive' => [
            'label' => 'Automotive',
            'keywords' => ['car dealer', 'auto repair', 'mechanic', 'car wash', 'tire', 'auto parts'],
        ],
        'real_estate' => [
            'label' => 'Real Estate',
            'keywords' => ['real estate', 'property', 'realtor', 'apartment', 'housing'],
        ],
        'legal' => [
            'label' => 'Legal Services',
            'keywords' => ['lawyer', 'attorney', 'law firm', 'legal', 'notary'],
        ],
        'finance' => [
            'label' => 'Finance & Insurance',
            'keywords' => ['bank', 'insurance', 'accounting', 'financial advisor', 'tax'],
        ],
        'construction' => [
            'label' => 'Construction & Home',
            'keywords' => ['contractor', 'plumber', 'electrician', 'construction', 'roofing', 'hvac'],
        ],
        'education' => [
            'label' => 'Education',
            'keywords' => ['school', 'university', 'college', 'tutor', 'training', 'academy'],
        ],
        'hotels' => [
            'label' => 'Hotels & Lodging',
            'keywords' => ['hotel', 'motel', 'resort', 'inn', 'hostel', 'bed and breakfast'],
        ],
        'fitness' => [
            'label' => 'Fitness & Sports',
            'keywords' => ['gym', 'fitness', 'yoga', 'sports', 'personal trainer', 'martial arts'],
        ],
        'pets' => [
            'label' => 'Pet Services',
            'keywords' => ['veterinary', 'pet store', 'pet grooming', 'dog training', 'pet sitting'],
        ],
        'technology' => [
            'label' => 'Technology & IT',
            'keywords' => ['computer repair', 'software', 'it services', 'web design', 'electronics'],
        ],
        'events' => [
            'label' => 'Events & Entertainment',
            'keywords' => ['wedding', 'photographer', 'dj', 'event planner', 'party', 'entertainment'],
        ],
        'travel' => [
            'label' => 'Travel & Tourism',
            'keywords' => ['travel agency', 'tour', 'tourism', 'airline', 'cruise'],
        ],
        'manufacturing' => [
            'label' => 'Manufacturing',
            'keywords' => ['factory', 'manufacturer', 'industrial', 'production', 'fabrication'],
        ],
        'agriculture' => [
            'label' => 'Agriculture',
            'keywords' => ['farm', 'agriculture', 'nursery', 'garden center', 'farming'],
        ],
        'other' => [
            'label' => 'Other / Custom',
            'keywords' => [],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Popular Countries
    |--------------------------------------------------------------------------
    */
    'countries' => [
        'US' => 'United States',
        'GB' => 'United Kingdom',
        'CA' => 'Canada',
        'AU' => 'Australia',
        'DE' => 'Germany',
        'FR' => 'France',
        'IT' => 'Italy',
        'ES' => 'Spain',
        'NL' => 'Netherlands',
        'BE' => 'Belgium',
        'CH' => 'Switzerland',
        'AT' => 'Austria',
        'SE' => 'Sweden',
        'NO' => 'Norway',
        'DK' => 'Denmark',
        'FI' => 'Finland',
        'IE' => 'Ireland',
        'PT' => 'Portugal',
        'PL' => 'Poland',
        'CZ' => 'Czech Republic',
        'NZ' => 'New Zealand',
        'SG' => 'Singapore',
        'HK' => 'Hong Kong',
        'JP' => 'Japan',
        'KR' => 'South Korea',
        'IN' => 'India',
        'AE' => 'United Arab Emirates',
        'SA' => 'Saudi Arabia',
        'ZA' => 'South Africa',
        'BR' => 'Brazil',
        'MX' => 'Mexico',
        'AR' => 'Argentina',
        'CL' => 'Chile',
        'CO' => 'Colombia',
        'MY' => 'Malaysia',
        'TH' => 'Thailand',
        'PH' => 'Philippines',
        'ID' => 'Indonesia',
        'VN' => 'Vietnam',
        'TR' => 'Turkey',
        'RU' => 'Russia',
        'EG' => 'Egypt',
        'NG' => 'Nigeria',
        'KE' => 'Kenya',
        'GH' => 'Ghana',
        'PK' => 'Pakistan',
        'BD' => 'Bangladesh',
    ],

    /*
    |--------------------------------------------------------------------------
    | Lead Types
    |--------------------------------------------------------------------------
    */
    'lead_types' => [
        'all' => [
            'label' => 'All Contacts',
            'description' => 'Collect email and phone numbers',
            'icon' => 'ri-contacts-book-line',
        ],
        'email' => [
            'label' => 'Email Only',
            'description' => 'Only businesses with email addresses',
            'icon' => 'ri-mail-line',
        ],
        'phone' => [
            'label' => 'Phone Only',
            'description' => 'Only businesses with phone numbers',
            'icon' => 'ri-phone-line',
        ],
        'whatsapp' => [
            'label' => 'WhatsApp Ready',
            'description' => 'Phone numbers suitable for WhatsApp',
            'icon' => 'ri-whatsapp-line',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Quality Score Thresholds
    |--------------------------------------------------------------------------
    */
    'quality_thresholds' => [
        'excellent' => 80,  // 80-100
        'good' => 60,       // 60-79
        'fair' => 40,       // 40-59
        'poor' => 0,        // 0-39
    ],

    /*
    |--------------------------------------------------------------------------
    | Rating Filters
    |--------------------------------------------------------------------------
    */
    'rating_filters' => [
        '' => 'Any Rating',
        '4.5' => '4.5+ Stars',
        '4.0' => '4.0+ Stars',
        '3.5' => '3.5+ Stars',
        '3.0' => '3.0+ Stars',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Quotas
    |--------------------------------------------------------------------------
    */
    'default_quotas' => [
        'daily' => 100,
        'monthly' => 2000,
    ],
];
