<?php

use Carbon\Carbon;
use App\Enums\StatusEnum;
use Predis\Response\Status;
use App\Enums\ContactAttributeEnum;

return [
    
    "email"                => "admin@xsender.test", 
    "phone"                => "", 
    "plugin"               => StatusEnum::FALSE->status(),
    "captcha"              => StatusEnum::FALSE->status(),
    "address"              => "", 
    "google_map_iframe"    => "", 
    "site_name"            => "Xsender", 
    "time_zone"            => "UTC",
    "country_code"         => "1",
    "currency_name"        => "USD",
    "currency_symbol"      => "$",
    "webhook_verify_token" => "xsender",
    "api_sms_method"       => StatusEnum::TRUE->status(),
    "app_link"             => "",
    
    "theme_dir"            => StatusEnum::FALSE->status(),
    "theme_mode"           => StatusEnum::TRUE->status(),

    // "login_with" => json_encode([
    //     'username',
    //     'email',
    //     'phone',
    // ]),

    "social_login"       => StatusEnum::FALSE->status(),
    "social_login_with"  => json_encode([
        'google_oauth' => [

            'status'        => StatusEnum::TRUE->status(),
            'client_id'     => '',
            'client_secret' => '',
        ],
        // 'facebook_oauth' => [

        //     'status'        => StatusEnum::TRUE->status(),
        //     'client_id'     => '5604901016291309',
        //     'client_secret' => '41c62bf15c8189171196ffde1d2a6848',
        // ],
    ]),
    "available_plugins"   => json_encode([
        'beefree'   => [
            'status'        => StatusEnum::FALSE->status(),
            'client_id'     => 'b2369021-3e95-4ca4-a8c8-2ed3e2531865',
            'client_secret' => 'uL3UKV8V4RLv77vodnNTM8e93np9OYsS5P2mJ0373Nt9ghbwoRbn'
        ],
    ]),

    "ai_functions" => StatusEnum::FALSE->status(),
    "ai_models"   => json_encode([
        'open_ai_text'   => [
            'status'   => StatusEnum::FALSE->status(),
            'provider' => 'openai',
            'base_url' => '',
            'model'    => 'gpt-4o-mini',
            'key'      => '',
        ],
        'open_ai_image'   => [
            'status'   => StatusEnum::FALSE->status(),
            'provider' => 'openai',
            'base_url' => '',
            'model'    => 'dall-e-3',
            'key'      => '',
        ],
    ]),

    "member_authentication" => json_encode([
        'registration'   => StatusEnum::TRUE->status(),
        'login'          => StatusEnum::TRUE->status(),
        // 'login_with'     => [
        //     'username',
        //     'email',
        //     'phone',
        // ]
    ]),
    "google_recaptcha" => json_encode([

        'status'     => StatusEnum::FALSE->status(),
        'key'        => '',
        'secret_key' => '',
    ]),

    "captcha_with_login"            => StatusEnum::FALSE->status(),
    "captcha_with_registration"     => StatusEnum::FALSE->status(),
    "registration_otp_verification" => StatusEnum::TRUE->status(),
    "email_otp_verification"        => StatusEnum::TRUE->status(),
    // "sms_otp_verification"          => StatusEnum::FALSE->status(),
    // "whatsapp_otp_verification"     => StatusEnum::FALSE->status(),
    "otp_expired_status"            => StatusEnum::FALSE->status(), //develop later
    // "sms_notifications"             => StatusEnum::FALSE->status(),
    "email_notifications"           => StatusEnum::TRUE->status(),
    // "whatsapp_notifications"        => StatusEnum::FALSE->status(),
    // "browser_notifications"         => StatusEnum::FALSE->status(),
    // "site_notifications"            => StatusEnum::FALSE->status(),

    // "default_sms_template"      => "hi {{name}}, {{message}}",
    "default_email_template"    => "hi, {{message}}",
    // "default_whatsapp_template" => "hi {{name}}, {{message}}",

    // "sms_delivery_method"       => StatusEnum::TRUE->status(),

    "contact_meta_data" => json_encode([
        "date_of_birth" => [
            "status" => StatusEnum::TRUE->status(),
            "type"   => ContactAttributeEnum::DATE->value
        ]
    ]),

    "last_cron_run"            => null,
    // "cron_pop_up"              => StatusEnum::FALSE->status(),
    "onboarding_bonus"         => StatusEnum::FALSE->status(),
    "onboarding_bonus_plan"    => null,
    "debug_mode"               => StatusEnum::FALSE->status(),
    "maintenance_mode"         => StatusEnum::FALSE->status(),
    "maintenance_mode_message" => "Please be advised that there will be scheduled downtime across our network from 12.00AM to 2.00AM",
    "landing_page"             => StatusEnum::TRUE->status(),

    "whatsapp_word_count"    => "320",
    "sms_word_count"         => "320",
    "sms_word_unicode_count" => "320",

    "primary_color"          => "#f25d6d",
    "secondary_color"        => "#f64b4d",
    "trinary_color"          => "#ffa360",
    "primary_text_color"     => "#ffffff",

    // Brand-neutral defaults for CodeCanyon fresh installs. Customer sets their
    // own via Admin → Settings → General → Copyright text / Copyright URL / etc.
    "copyright"              => "Your Company",
    "copyright_url"          => "#",
    "support_url"            => "#",
    "help_center_url"        => "#",

    // v5.0-S2 — Unified Inbox inbound email domain. Customer configures the
    // MX record for this subdomain to forward to their SendGrid Inbound Parse
    // / Mailgun Route / Postmark Inbound / postfix->curl relay, which POSTs
    // to /webhooks/inbound-email. Empty = feature disabled (no Reply-To
    // rewrite happens, no inbound webhook posts land conversations).
    "inbound_reply_domain"   => "",
    
    "mime_types" => json_encode([
        'png', 
        'jpg', 
        'jpeg',
        'webp'
    ]),
    "max_file_size"   => 20000,
    "max_file_upload" => 4,
    // "storage_unit"    => "KB",
    // 'storage'         => "local",
    // "store_as_webp"   => StatusEnum::FALSE->status(),

    // Email Attachment Settings
    "email_attachment_max_files" => 5,
    "email_attachment_max_size"  => 10, // MB per file

    // Email Tracking
    "email_tracking_enabled" => StatusEnum::TRUE->status(),
    "email_open_tracking" => StatusEnum::TRUE->status(),
    "email_click_tracking" => StatusEnum::TRUE->status(),

    // Bounce & Suppression
    "bounce_auto_suppress" => StatusEnum::TRUE->status(),
    "bounce_soft_threshold" => 3,

    // DKIM / Sending Domains
    "dkim_enabled" => StatusEnum::TRUE->status(),
    "default_dkim_selector" => "xsender",
    "max_sending_domains_per_user" => 3,
    "max_tracking_domains_per_user" => 2,

    "currencies" => json_encode([

        "USD" => [
            "name"   => "United States Dollar",
            "symbol" => "$",
            "rate"   => "1",
            "status" => StatusEnum::TRUE->status(),
            "is_default" => StatusEnum::TRUE->status()
        ],
        "BDT" => [
            "name"   => "Bangladeshi Taka",
            "symbol" => "৳",
            "rate"   => "114",
            "status" => StatusEnum::FALSE->status(),
            "is_default" => StatusEnum::FALSE->status()
        ],
    ]),

    "paginate_number" => 10,

    // Automation mode: auto, cron_url, scheduler, supervisor
    // Prevents double message sending when multiple automation methods are configured
    "automation_mode" => "auto",

    // Contact Management Settings
    // When enabled, contacts from single message sends are saved to "Single Contact" group for reuse
    // When disabled, contacts are deleted after message is sent (reduces database bloat)
    "auto_save_quick_send_contacts" => StatusEnum::TRUE->status(),

    // Filter duplicate contacts: When enabled, prevents duplicate contacts in the same group
    "filter_duplicate_contact" => StatusEnum::TRUE->status(),

    "auth_heading" => "Start turning your ideas into reality.",
    "authentication_background" => "6885a6dc1e4861753589468.webp",
    "authentication_background_inner_image_one" => "6885a6dc4667a1753589468.webp",
    "authentication_background_inner_image_two" => "6885a6dc4cfbc1753589468.webp",
    "meta_title" => "Welcome To Xsender",
    "meta_description" => "Start your marketing journey today",
    "meta_keywords" => json_encode([
        "bulk",
        "sms",
        "email",
        "whatsapp",
        "marketing"
    ]),
    "site_logo" => "66e9dd6484e241726602596.webp",
    "site_square_logo" => "6885a6e7ef1af1753589479.png",
    "panel_logo" => "66e9dd64e9c721726602596.webp",
    "panel_square_logo" => "6885a6e7f2d121753589479.png",
    "favicon" => "66e9dd65033111726602597.webp",
    "meta_image" => "66e9dd65076b11726602597.webp",
    "frontend_active_theme" => "default"
];
