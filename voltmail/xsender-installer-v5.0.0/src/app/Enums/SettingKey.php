<?php

namespace App\Enums;

use App\Enums\EnumTrait;

enum SettingKey: string
{
    use EnumTrait;

    ## Global 

    # Email Verification related keys
    case EMAIL_CONTACT_VERIFICATION     = "email_contact_verification";
    case VERIFY_EMAIL_ADDITIONAL_CHECKS = "verify_email_additional_checks";

    case INVALID_SYNTAX                 = "invalid_syntax";
    case INVALID_SYNTAX_MESSAGE         = "invalid_syntax_message";

    case INVALID_DOMAIN                 = "invalid_domain";
    case INVALID_DOMAIN_MESSAGE         = "invalid_domain_message";

    case DISPOSABLE_DOMAIN              = "disposable_domain";
    case DISPOSABLE_DOMAIN_LIST         = "disposable_domain_list";
    case DISPOSABLE_DOMAIN_MESSAGE      = "disposable_domain_message";

    case DOMAIN_TYPOS                   = "domain_typos";
    case DOMAIN_TYPO_MESSAGE            = "domain_typo_message";

    case COMMON_DOMAIN                  = "common_domain";

    case ROLE_BASED_EMAIL               = "role_based_email";
    case EMAIL_ROLE_LIST                = "email_role_list";
    case ROLE_BASED_MESSAGE             = "role_based_message";

    case CHECK_TLD                      = "check_tld";
    case TLD_LIST                       = "tld_list";
    case TLD_MESSAGE                    = "tld_message";

    # Contact Related Keys
    case CONTACT_META_DATA          = "contact_meta_data";
    case FILTER_DUPLICATE_CONTACT   = "filter_duplicate_contact";

    # Admin

    # Setting Group 

    # Setting Sub-Group

    # Setting Keys
    case AI_FUNCTIONS   = "ai_functions";
    case AI_MODELS      = "ai_models";

    // Array keys 
    case MEMBER_AUTHENTICATION  = "member_authentication";
    case SOCIAL_LOGIN_WITH      = "social_login_with";
    case ANDROID_OFF_CANVAS_GUIDE   = "android_off_canvas_guide";
    case WHATSAPP_OFF_CANVAS_GUIDE  = "whatsapp_off_canvas_guide";

    //Active or Inactive Keys
    case LOGIN                          = "login";
    case REGISTRATION                   = "registration";
    case MAINTENANCE_MODE               = "maintenance_mode";
    case ONBOARDING_BONUS               = "onboarding_bonus";
    case EMAIL_OTP_VERIFICATION         = "email_otp_verification";
    case REGISTRATION_OTP_VERIFICATION  = "registration_otp_verification";
    

    //Other Keys
    case SITE_NAME                  = "site_name";
    case AUTH_HEADING               = "auth_heading";
    case PAGINATE_NUMBER            = "paginate_number";
    case MAINTENANCE_MODE_MESSAGE   = "maintenance_mode_message";
    case ONBOARDING_BONUS_PLAN      = "onboarding_bonus_plan";
    case QUEUE_TOKEN                = "queue_token";
    case QUEUE_CONNECTION_CONFIG    = "queue_connection_config";
    case FORCE_SSL                  = "force_ssl";
    case FRONTEND_ACTIVE_THEME      = "frontend_active_theme";

    //Meta Keys
    case META_APP_ID            = "meta_app_Id";
    case META_APP_SECRET        = "meta_app_secret";
    case META_API_VERSION       = "meta_api_version";
    
    
    # User Keys

    # Image Keys

    case ANDROID_OFF_CANVAS_IMAGE = "android_off_canvas_image";
    
    # Static values
    case SUPPORT_URL        = "https://support.igensolutionsltd.com/";
    case EMAIL_CONTACT      = "email_contact";
    case ALL                = "all";
    case ELEMENT_CONTENT    = "element_content";
    case ROUTE_USER_DASHBOARD = "/user/dashboard";
    case SINGLE_CONTACT_GROUP_NAME = "Single Contact";
    case AUTO_SAVE_QUICK_SEND_CONTACTS = "auto_save_quick_send_contacts"; // When disabled, contacts from single sends are deleted after dispatch

    # Events
}
