<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Support URL
    |--------------------------------------------------------------------------
    |
    | This URL is used for error messages shown to end users in production.
    | For white-label deployments, set SUPPORT_URL in .env to customize this.
    | Example: SUPPORT_URL=https://your-company-support.com
    |
    */
    // Fallback support URL when site_settings('support_url') is empty. Overridable
    // per-install via Admin → Settings → General → Support URL, or via env
    // SUPPORT_URL. Empty '#' default keeps CodeCanyon fresh installs from
    // shipping our brand as the target of every "Support" link.
    "support_url" => env('SUPPORT_URL', '#'),

    "google" => [
        'g_client_id'     => "####",
        'g_client_secret' => "###",
        'g_client_status' => "#####",
    ],

    "gateway_credentials" => [
        "sms" => [
            "default_gateway_id" => 1,
            
            "nexmo" => [
                "meta_data" => [
                    "api_key"    => "####",
                    "api_secret" => "####",
                    "sender_id"  => "####"
                ],
                "native_bulk_support" => false
            ],
            "twilio" => [
                "meta_data" => [
                    "account_sid" => "####",
                    "auth_token"  => "####",
                    "from_number" => "####",
                    "sender_id"   => "####"
                ],
                "native_bulk_support" => false
            ],
            "messagebird" => [
                "meta_data" => [
                    "access_key" => "####",
                    "sender_id"  => "####",
                ],
                "native_bulk_support" => true
            ],
            "textmagic" => [
                "meta_data" => [
                    "api_key"             => "####",
                    "text_magic_username" => "#####",
                    "sender_id"           => "####"
                ],
                "native_bulk_support" => true
            ],
            "clickatell" => [
                "meta_data" => [
                    "clickatell_api_key" => "####",
                    "sender_id"          => "####"
                ], 
                "native_bulk_support" => true
            ],
            "infobip" => [
                "meta_data" => [
                    "infobip_base_url" => "####",
                    "infobip_api_key"  => "####",
                    "sender_id"        => "####"
                ],
                "native_bulk_support" => true
            ],
            "smsbroadcast" => [
                "meta_data" => [
                    "sms_broadcast_username" => "####",
                    "sms_broadcast_password" => "####", 
                ], 
                "native_bulk_support" => true
            ],
            "mimsms"       => [
                "meta_data" => [
                    "api_url"   => "###",
                    "api_key"   => "###",
                    "sender_id" => "###"
                ],
                "native_bulk_support" => true
            ],
            "ajurasms" => [
                "meta_data" => [
                    "api_url"    => "###",
                    "api_key"    => "###",
                    "secret_key" => "###",
                    "sender_id"  => "###"
                ],
                "native_bulk_support" => true

            ],
            "msg91" => [
                "meta_data" => [
                    "api_url"   => "###",
                    "auth_key"  => "###",
                    "flow_id"   => "###",
                    "sender_id" => "###"
                ], 
                "native_bulk_support" => true
            ]
        ],
   
        "email" => [
            'smtp' => [
                "meta_data" => [
                    'host'       => 'smtp.mailtrap.io',
                    'driver'     => 'SMTP',
                    'port'       => '2525',
                    'encryption' => [

                        'Standard encryption (TLS)' => 'tls',
                        'Secure encryption (SSL)'   => 'ssl',
                        'PowerMTA Server'           => 'pwmta',
                        'STARTTLS'                  => 'starttls',
                        'None or No SSL'            => 'none',
                    ],
                    'username'   => 'Username',
                    'password'   => 'Password',
                ],
                "native_bulk_support" => false
            ],
			'sendgrid'=>[
				"meta_data" => [
                    'secret_key' => 'Api Secret Key',
                ],
                "native_bulk_support" => false
			],
			'aws' =>[
				"meta_data" => [
                    'profile'      => 'Ses Profile',
                    'version'      => 'Ses Version',
                    'region'       => 'Ses Region',
                    'sender_email' => 'Ses Sender Email ',
                ], 
                "native_bulk_support" => true
			],
			'mailjet' => [
                "meta_data" => [
                    'secret_key' => 'Api Secret Key',
                    'api_key' => 'Api Public Key'
                ], 
                "native_bulk_support" => true
            ]
			,
			'mailgun' => [
				"meta_data" => [
                    'secret_key'      => 'Api Secret Key',
				    'verified_domain' => 'Verified Domain'
                ],
                "native_bulk_support" => true
			],
        ],

        "whatsapp" => [
            "node" => [
                "native_bulk_support" => false
            ],
            "cloud" => [
                "meta_data" => [
                    'user_access_token'            => "###",
                    'phone_number_id'              => "###",
                    'whatsapp_business_account_id' => "###",
                ],
                "native_bulk_support" => false
            ],
        ]
    ],

    "recaptcha" => [
        'recaptcha_key'    => "####",
        'recaptcha_secret' => "###",
        'recaptcha_status' => "2",
    ],
    
    "webhook" => [
        'callback_url' => "####",
        'verify_token' => "###",
    ],

    "whatsapp_business_credentials" => [

        'default' => [

            'version'           => "v26.0",
        ],
        'required' => [

            'user_access_token'            => "###",
            'phone_number_id'              => "###",
            'whatsapp_business_account_id' => "###",
        ],
        'optional' => [

            'business_id'       => "###",
        ]
        
    ],

    'file_types'=> ['3dmf',    '3dm',    'avi',    'ai',    'bin',    'bin',    'bmp',    'cab',    'c',    'c++',    'class',    'css',    'csv',    'cdr',    'doc',    'dot',    'docx',    'dwg',    'eps',    'exe',    'gif',    'gz',    'gtar',    'flv',    'fh4',    'fh5',    'fhc',    'help',    'hlp',    'html',    'htm',    'ico',    'imap',    'inf',    'jpe',    'jpeg',    'jpg',    'js',    'java',    'latex',    'log',    'm3u',    'midi',    'mid',    'mov',    'mp3',    'mpeg',    'mpg',    'mp2',    'ogg',    'phtml',    'php',    'pdf',    'pgp',    'png',    'pps',    'ppt',    'ppz',    'pot',    'ps',    'qt',    'qd3d',    'qd3',    'qxd',    'rar',    'ra',    'ram',    'rm',    'rtf',    'spr',    'sprite',    'stream',    'swf',    'svg',    'sgml',    'sgm',    'tar',    'tiff',    'tif',    'tgz',    'tex',    'txt',    'vob',    'wav',    'wrl',    'wrl',    'xla',    'xls',    'xls',    'xlc',    'xml',    'xlsx',    'zip',   'webp'],

    "login_attribute" => [
        'username',
        'phone',
        'email'
    ],

    "logo_keys" => [
        "site_logo",
        "site_square_logo",
        "panel_logo",
        "panel_square_logo",
        "favicon",
        // "loader_icon",
        "meta_image",
    ],

    "auth_image_keys" => [
        "authentication_background",
        "authentication_background_inner_image_one",
        "authentication_background_inner_image_two",
    ],

    "file_path" =>  [
        'frontend_theme' => [
            'path'              => 'assets/file/frontend_theme/',
            'fall_back_path'    => 'assets/file/default/'
        ],
        'android_off_canvas_guide' => [
            'path'              => 'assets/file/images/channel/android',
            'fall_back_path'    => 'assets/file/default/',
            'size'              => '600x400',
        ],
        'whatsapp_off_canvas_guide' => [
            'path'              => 'assets/file/images/channel/whatsapp',
            'fall_back_path'    => 'assets/file/default/',
            'size'              => '600x400',
        ],
        'authentication_background' => [
            'path' => 'assets/file/images/global/auth/main',
            'size' => '960x865',
        ],
        'authentication_background_inner_image_one' => [
            'path' => 'assets/file/images/global/auth/child/one',
            'size' => '494x300',
        ],
        'authentication_background_inner_image_two' => [
            'path' => 'assets/file/images/global/auth/child/two',
            'size' => '494x300',
        ],
        'admin_profile' => [
            'path' => 'assets/file/images/backend/admin_profile',
            'size' => '350x350',
        ],
        
        'automatic_payment' => [
            'path' => 'assets/file/images/global/automatic_payment',
            'size' => '350x350',
        ],
        'manual_payment' => [
            'path' => 'assets/file/images/global/manual_payment',
            'size' => '350x350',
        ],
        'withdraw_request' => [
            'path' => 'assets/file/images/user/withdraw_request',
            'size' => '350x350',
        ],

        'site_logo' => [
            'path' => 'assets/file/images/backend/site_logo',
            'size' => '438x117',
        ],
        'site_square_logo' => [
            'path' => 'assets/file/images/backend/site_square_logo',
            'size' => '160x160',
        ],
        'panel_logo' => [
            'path' => 'assets/file/images/global/panel_logo',
            'size' => '350x75',
        ],
        'panel_square_logo' => [
            'path' => 'assets/file/images/global/panel_square_logo',
            'size' => '160x160',
        ],
        'favicon' => [
            'path' => 'assets/file/images/global/favicon',
            'size' => '16x16',
        ],
        'meta_image' => [
            'path' => 'assets/file/images/global/meta_image',
            'size' => '350x75',
        ],
        'blog_images' => [
            'path' => 'assets/file/images/global/blog_images',
            'size' => '1165x777',
        ],
        'ai_images' => [
            'path' => 'assets/file/images/ai',
        ],
        'social_login' => [
            'google' => [
                'path' => 'assets/file/images/global/social_login',
                'size' => '512x512',
            ]
        ],
        'frontend' => [
            'banner_image' => [
                'path' => 'assets/file/images/frontend/banner_image',
                'size' => '1616x998',
            ],
            'banner_second_image' => [
                'path' => 'assets/file/images/frontend/banner_second_image',
                'size' => '1200x490',
            ],
            'video_button_image' => [
                'path' => 'assets/file/images/frontend/video_button_image',
                'size' => '600x250',
            ],
            'payment_gateway_image' => [
                'path' => 'assets/file/images/frontend/payment_gateway_image',
                'size' => '608x60',
            ],
            'unsubscription_image' => [
                'path' => 'assets/file/images/frontend/unsubscription_image',
                'size' => '200x200',
            ],
            'sms_service_image' => [
                'path' => 'assets/file/images/frontend/sms_service_image',
                'size' => '1136x866',
            ],
            'whatsapp_service_image' => [
                'path' => 'assets/file/images/frontend/whatsapp_service_image',
                'size' => '1136x866',
            ],
            'email_service_image' => [
                'path' => 'assets/file/images/frontend/email_service_image',
                'size' => '1136x866',
            ],
            'plan_breadcrumb_image' => [
                'path' => 'assets/file/images/frontend/plan_breadcrumb_image',
                'size' => '1920x1080',
            ],
            'about_breadcrumb_image' => [
                'path' => 'assets/file/images/frontend/about_breadcrumb_image',
                'size' => '1920x1080',
            ],
            'about_overview_image' => [
                'path' => 'assets/file/images/frontend/about_overview_image',
                'size' => '1410x964',
            ],
            'contact_breadcrumb_image' => [
                'path' => 'assets/file/images/frontend/contact_breadcrumb_image',
                'size' => '1920x1080',
            ],
            'blog_breadcrumb_image' => [
                'path' => 'assets/file/images/frontend/blog_breadcrumb_image',
                'size' => '1920x1080',
            ],
            'service_breadcrumb_image' => [
                'path' => 'assets/file/images/frontend/service_breadcrumb_image',
                'size' => '1920x1080',
            ],
            'policy_breadcrumb_image' => [
                'path' => 'assets/file/images/frontend/policy_breadcrumb_image',
                'size' => '1920x1080',
            ],
            'service_overview_image' => [
                'path' => 'assets/file/images/frontend/service_overview_image',
                'size' => '700x525',
            ],
            'service_details_image' => [
                'path' => 'assets/file/images/frontend/service_details_image',
                'size' => '1010x690',
            ],
            'service_highlight_image' => [
                'path' => 'assets/file/images/frontend/service_highlight_image',
                'size' => '1410x964',
            ],
            
            'element_content' => [
                'banner' => [
                    'company_logo' => [
                        'path' => 'assets/file/images/frontend/element_content/banner/company_logo',
                        'size' => '325x110',
                    ]   
                    ],
                'feature' => [
                    'feature_image' => [
                        'path' => 'assets/file/images/frontend/element_content/feature/feature_image',
                        'size' => '978x1462',
                    ]   
                ],
                'workflow' => [
                    'process_image' => [
                        'path' => 'assets/file/images/frontend/element_content/workflow/process_image',
                        'size' => '1200x675',
                    ]   
                ],
                'feedback' => [
                    'reviewer_image' => [
                        'path' => 'assets/file/images/frontend/element_content/feedback/reviewer_image',
                        'size' => '740x740',
                    ]   
                ],
                'gateway' => [
                    'gateway_image' => [
                        'path' => 'assets/file/images/frontend/element_content/gateway/gateway_image',
                        'size' => '400x70',
                    ]   
                ],
                'service_feature' => [
                    'service_feature_image' => [
                        'path' => 'assets/file/images/frontend/element_content/service/service_feature_image',
                        'size' => '240x240',
                    ],
                ],
                'connect_section' => [
                    'conenct_image' => [
                        'path' => 'assets/file/images/frontend/element_content/connect_section/conenct_image',
                        'size' => '200x200',
                    ]   
                ],
            ]
        ],
        'whatsapp_meta_chats' => [
            'path' => 'assets/file/chats/whatsapp/meta/incoming',
        ],

    ],

    "ai" => [
        "manual_suggestions" => [
            "Make it professional",
            "Add humor",
            "Simplify language"
        ],
         "image_resolution" => [
           'dall-e-2' => ['256x256', '512x512', '1024x1024'],
            'dall-e-3' => ['1024x1024', '1792x1024', '1024x1792']
        ],
        // "model" => [
        //     'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
        //     'gpt-4' => 'GPT-4',
        //     'gpt-4o' => 'GPT-4o',
        // ],
        // "default_model" => 'gpt-3.5-turbo',
    ],

    'ai_image_generation_model' => [
        'dall-e-2'        => 'DALL-E-2',
        'dall-e-3'        => 'DALL-E-3',
    ],
    'ai_image_secret' => [
        'open_ai'       => 'Open AI',
    ],

    'ai_image_resolution' => [
        '256x256'   => '256x256',
        '512x512'   => '512x512',
        '1024x1024' => '1024x1024',
        '1792x1024' => '1792x1024',
        '1024x1792' => '1024x1792'

    ],

    'ai_image_quality' => [
        'standard'  => 'Standard',
        'hd'        => 'HD',
    ],

    "json_object" => [
        'currencies',
        'social_login_with',
        'available_plugins',
        'ai_models',
        'member_authentication',
        'google_recaptcha',
        'contact_meta_data',
        'mime_types',
        'meta_keywords',
        'accessible_email_gateways',
        'accessible_sms_api_gateways',
        'accessible_sms_android_gateways',
        'verify_email_additional_checks',
        'disposable_domain_list',
        'email_role_list',
        'tld_list',
        'common_domain',
        'android_off_canvas_guide',
        'whatsapp_off_canvas_guide',
        'queue_connection_config',
    ],

    'queue_connection_config' => [
        'driver' => 'database',
        'connection' => [
            'host' => null,
            'port' => null,
            'database' => null,
            'username' => null,
            'password' => null,
        ],
    ],

    "android_off_canvas_guide" => [
        "written_guide" => [
            "message" => "1. Open Xsender App on your phone\n2. Scan The QR code below from the App\n3. Reload web panel after successful connection\n4. Connect Sims within the App\n5. Start dispatching messages from the web"
        ],
        "external_guide" => [
            "text" => "Need help to get started?",
            "link" => "https://support.igensolutionsltd.com/"
        ],
        "image" => [
            "name" => "default-off-canvas-image.png"
        ]
    ],

    "whatsapp_off_canvas_guide" => [
        "written_guide" => [
            "message" => "1. Open WhatsApp On Your Phone\n2. Tap Menu Or Settings And Select Linked Devices\n3. Point Your Phone To This Screen To Capture The Code"
        ],
        "external_guide" => [
            "text" => "Need help to get started?",
            "link" => "https://support.igensolutionsltd.com/"
        ],
        "image" => [
            "name" => "default-off-canvas-image.jpg"
        ]
    ],
];

