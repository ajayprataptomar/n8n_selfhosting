<?php

namespace App\Enums;

enum MetaApiEndpoints: string
{
    /**
     * Current Meta Graph API version
     * Single source of truth — update here for all services
     */
    const CURRENT_VERSION = 'v26.0';

    // SDK & OAuth
    case JS_SDK                     = "https://connect.facebook.net/en_US/sdk.js";
    case OAUTH_ACCESS_TOKEN         = '/oauth/access_token';
    case OAUTH_DEBUG_TOKEN          = '/debug_token';

    // User Info
    case USER_INFO                  = '/me';
    case USER_ACCOUNTS              = '/me/accounts';
    case USER_BUSINESS_ACCOUNTS     = '/me/businesses';

    // WhatsApp Business Account (WABA)
    case PHONE_NUMBERS              = '/:business_account_id/phone_numbers';
    case WABA_INFO                  = '/:waba_id';
    case WABA_PHONE_NUMBERS         = '/:waba_id/phone_numbers';
    case WABA_MESSAGE_TEMPLATES     = '/:waba_id/message_templates';
    case WABA_SUBSCRIBED_APPS       = '/:waba_id/subscribed_apps';

    // Phone Number Management
    case PHONE_NUMBER_INFO          = '/:phone_number_id';
    case PHONE_NUMBER_REGISTER      = '/:phone_number_id/register';
    case PHONE_NUMBER_DEREGISTER    = '/:phone_number_id/deregister';
    case PHONE_NUMBER_REQUEST_CODE  = '/:phone_number_id/request_code';
    case PHONE_NUMBER_VERIFY_CODE   = '/:phone_number_id/verify_code';

    // Messaging
    case SEND_MESSAGE               = '/:phone_number_id/messages';

    // Message Templates (use WABA_MESSAGE_TEMPLATES for create/list, TEMPLATE_INFO for get/delete specific)
    case TEMPLATE_INFO              = '/:template_id';

    // Business Manager
    case BUSINESS_INFO              = '/:business_id';
    case BUSINESS_OWNED_WABAS       = '/:business_id/owned_whatsapp_business_accounts';
    case BUSINESS_CLIENT_WABAS      = '/:business_id/client_whatsapp_business_accounts';

    // System User (Tech Provider)
    case SYSTEM_USER_INFO           = '/:system_user_id';
    case SYSTEM_USER_ACCESS_TOKENS  = '/:system_user_id/access_tokens';
    case SYSTEM_USER_ASSIGN_WABA    = '/:waba_id/assigned_users';

    // Webhooks
    case WEBHOOK_SUBSCRIPTION       = '/:app_id/subscriptions';
    case WEBHOOK_FIELDS             = '/:app_id/subscribed_apps';

    // Analytics & Insights
    case ANALYTICS                  = '/:waba_id/analytics';
    case CONVERSATION_ANALYTICS     = '/:waba_id/conversation_analytics';

    // Media
    case UPLOAD_MEDIA               = '/:phone_number_id/media';
    case MEDIA_INFO                 = '/:media_id';  // Use for GET/DELETE media (same endpoint, different HTTP methods)

    /**
     * Get the base URL for Meta Graph API
     */
    public static function getGraphBaseUrl(): string
    {
        return 'https://graph.facebook.com';
    }

    /**
     * Get the base URL for Meta OAuth
     */
    public static function getOAuthBaseUrl(): string
    {
        return 'https://www.facebook.com';
    }

    /**
     * Build full URL with version
     */
    public function buildUrl(string $version = self::CURRENT_VERSION, array $params = []): string
    {
        $endpoint = $this->value;

        // Replace path parameters
        foreach ($params as $key => $value) {
            $endpoint = str_replace(":{$key}", $value, $endpoint);
        }

        // Handle special cases
        if ($this === self::JS_SDK) {
            return $this->value;
        }

        if ($this === self::OAUTH_ACCESS_TOKEN || $this === self::OAUTH_DEBUG_TOKEN) {
            return self::getGraphBaseUrl() . $endpoint;
        }

        return self::getGraphBaseUrl() . "/{$version}" . $endpoint;
    }

    /**
     * Get required permissions for this endpoint
     */
    public function getRequiredPermissions(): array
    {
        return match ($this) {
            self::SEND_MESSAGE => ['whatsapp_business_messaging'],
            self::WABA_INFO, self::WABA_PHONE_NUMBERS, self::WABA_MESSAGE_TEMPLATES => ['whatsapp_business_management'],
            self::PHONE_NUMBER_REGISTER, self::PHONE_NUMBER_DEREGISTER => ['whatsapp_business_management'],
            self::BUSINESS_INFO, self::BUSINESS_OWNED_WABAS, self::BUSINESS_CLIENT_WABAS => ['business_management'],
            default => [],
        };
    }

    /**
     * Check if endpoint requires System User token
     */
    public function requiresSystemUserToken(): bool
    {
        return match ($this) {
            self::SYSTEM_USER_INFO, self::SYSTEM_USER_ACCESS_TOKENS, self::SYSTEM_USER_ASSIGN_WABA => true,
            self::BUSINESS_OWNED_WABAS, self::BUSINESS_CLIENT_WABAS => true,
            default => false,
        };
    }
}
