<?php

use App\Enums\SettingKey;

/**
 * Configures demo mode functionality for HTTP method restrictions and database resets.
 *
 * Structure:
 * - `enabled`: Boolean (via APP_MODE env, 'demo' enables). Toggles demo mode.
 * - `messages`: Contains `global` message, used as fallback for restrictions.
 * - `method_usage`: Array of HTTP methods (e.g., 'GET', 'POST') with:
 *   - `enabled`: Boolean. True allows method (blacklist applies), false blocks (whitelist or blacklist applies).
 *   - `message`: Message for restricted methods.
 *   - `whitelisted_routes`: Routes exempt from restriction (when enabled=false).
 *   - `blacklisted_routes`: Routes to restrict (when enabled=true or enabled=false).
 *   - `priority`: 'whitelisted_routes' or 'blacklisted_routes' (default 'whitelisted_routes'). Resolves conflicts.
 * - `database_reset_unit`: Time unit for resets ('second', 'minute', 'hour', 'day', 'month', 'year').
 * - `database_reset_duration`: Integer duration for reset intervals.
 *
 * Restriction Logic:
 * - If `method_usage[method]` undefined, method allowed.
 * - If `enabled=true`:
 *   - No `blacklisted_routes`: Allow all routes.
 *   - Empty `blacklisted_routes`: Block all routes.
 *   - Non-empty `blacklisted_routes`: Block listed routes.
 *   - Non-empty `whitelisted_routes`: Allow listed routes, override blacklist if conflict (based on priority).
 * - If `enabled=false`:
 *   - Non-empty `blacklisted_routes`: Block listed routes, allow others.
 *   - No or empty `blacklisted_routes` and no `whitelisted_routes`: Block all routes.
 *   - No or empty `blacklisted_routes` and non-empty `whitelisted_routes`: Allow listed routes, block others.
 * - If both lists present and route in both, `priority` decides (`whitelisted_routes` allows, `blacklisted_routes` blocks).
 *
 * Messaging:
 * - Restricted methods use `method_usage[method][message]` or `messages.global` fallback.
 *
 * Database Reset:
 * - If `enabled=true`, resets database using SQL file at `resource_path('database/database_demo.sql')`.
 * - Resets based on `database_reset_unit` and `database_reset_duration` (e.g., every 1 hour).
 * - Tracks last reset in `storage/demo_reset.json`.
 *
 * Example:
 * - `GET` with `enabled=false`, `blacklisted_routes=['api.incoming.whatsapp.send.query']`: Blocks `api.incoming.whatsapp.send.query`, allows others.
 * - `POST` with `enabled=false`, `whitelisted_routes=['admin.login']`: Allows `admin.login`, blocks others.
 * - `GET` with `whitelisted_routes=['admin.login']`, `blacklisted_routes=['admin.login']`, `priority='blacklisted_routes'`: Blocks `admin.login`.
 */

return [
    'enabled'   => env('APP_MODE', 'live') === 'demo',
    'messages'  => [
        'global' => 'This is a demo environment. Some actions are restricted.',
    ],
    'database_reset_unit'       => 'hour',
    'database_reset_duration'   => 1,
    'method_usage' => [
        'GET' => [
            'enabled' => false,
            'message' => 'GET method usage is restricted in demo mode.',
            'whitelisted_routes' => [],
            'blacklisted_routes' => [
                'api.incoming.whatsapp.send.query',
                'api.incoming.email.send.query',
                'api.incoming.sms.send.query',
            ],
            'priority' => 'whitelisted_routes',
        ],
        'POST' => [
            'enabled' => true,
            'message' => 'This action is restricted in demo mode.',
            'whitelisted_routes' => [],
            'blacklisted_routes' => [
                // Block password & profile changes
                'admin.password.update',
                'admin.profile.update',
                'user.password.update',
                'user.profile.update',

                // Block system settings changes (logo, site name, config)
                'admin.system.setting.store',
                'admin.system.setting.update',
                'admin.system.setting.general.update',

                // Block frontend content changes (Frontend Sections editor)
                'admin.frontend.sections.save.content',
                'admin.frontend.sections.element.delete',

                // Block blog CRUD (xsender uses POST for save/delete, not REST methods)
                'admin.blog.save',
                'admin.blog.delete',
                'admin.blog.status.update',
                'admin.blog.bulk',

                // Block admin template changes (email / SMS / WhatsApp / Node templates)
                'admin.template.store',
                'admin.template.approve',
                'admin.template.status.update',
                'admin.template.node.store',

                // Block user/admin management
                'admin.user.store',
                'admin.user.update',
                'admin.user.status.update',

                // Block plan/subscription changes
                'admin.membership.plan.store',
                'admin.membership.plan.update',

                // Block gateway credential changes
                'admin.gateway.sms.store',
                'admin.gateway.sms.update',
                'admin.gateway.email.store',
                'admin.gateway.email.update',

                // Block language/translation changes
                'admin.system.language.store',
                'admin.system.language.update',
                'admin.system.language.destroy',
                'admin.system.language.translate.update',

                // Block currency changes
                'admin.system.currency.store',
                'admin.system.currency.update',

                // Block addon management
                'admin.addon.install',
                'admin.addon.activate',
                'admin.addon.deactivate',
                'admin.addon.uninstall',

                // Block system update
                'admin.system.update',
                'admin.system.install.update',
            ],
            'priority' => 'blacklisted_routes',
        ],
        'PUT' => [
            'enabled' => true,
            'message' => 'This action is restricted in demo mode.',
            'whitelisted_routes' => [],
            'blacklisted_routes' => [
                'admin.password.update',
                'admin.profile.update',
                'user.password.update',
                'user.profile.update',
                'admin.system.setting.store',
                'admin.system.setting.update',
                'admin.frontend.sections.update',
                'admin.membership.plan.update',
                'admin.gateway.sms.update',
                'admin.gateway.email.update',
                'admin.system.language.update',
                'admin.system.language.translate.update',
                'admin.system.currency.update',

                // Block admin template edits
                'admin.template.update',
                'admin.template.node.update',

                // Block frontend section + blog edits (PUT path, if any)
                'admin.frontend.sections.save.content',
            ],
            'priority' => 'blacklisted_routes',
        ],
        'PATCH' => [
            'enabled' => true,
            'message' => 'This action is restricted in demo mode.',
            'whitelisted_routes' => [],
            'blacklisted_routes' => [
                'admin.password.update',
                'admin.profile.update',
                'user.password.update',
                'user.profile.update',
                'admin.system.setting.store',
            ],
            'priority' => 'blacklisted_routes',
        ],
        'DELETE' => [
            'enabled' => true,
            'message' => 'Deleting items is restricted in demo mode.',
            'whitelisted_routes' => [],
            'blacklisted_routes' => [
                // Block all delete operations on core data
                'admin.user.destroy',
                'admin.membership.plan.destroy',
                'admin.gateway.sms.destroy',
                'admin.gateway.email.destroy',
                'admin.frontend.sections.element.delete',
                'admin.template.destroy',
                'admin.template.node.destroy',
                'admin.system.language.destroy',
                'admin.system.currency.destroy',
                'admin.addon.uninstall',

                // Block contact/campaign deletion
                'admin.contact.destroy',
                'admin.contact.group.destroy',
                'user.contact.destroy',
                'user.contact.group.destroy',
            ],
            'priority' => 'blacklisted_routes',
        ],
        'HEAD' => [
            'enabled' => true,
            'message' => 'HEAD method usage is restricted in demo mode.',
            'whitelisted_routes' => [],
            'blacklisted_routes' => [],
            'priority' => 'whitelisted_routes',
        ],
        'OPTIONS' => [
            'enabled' => true,
            'message' => 'OPTIONS method usage is restricted in demo mode.',
            'whitelisted_routes' => [],
            'blacklisted_routes' => [],
            'priority' => 'whitelisted_routes',
        ],
    ]
];