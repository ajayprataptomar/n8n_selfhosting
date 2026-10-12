<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * v5.0 — expose the Unified Inbox as a plan-toggleable feature.
 *
 * Idempotent: skips if plan_display_features table missing (fresh
 * v4.x install without v4.3+ migrations) or if the feature row already
 * exists. Does NOT flip is_allowed on existing plans — customer's admin
 * decides which plans get the inbox after upgrading.
 *
 * Route-side gate lives in App\Http\Controllers\User\Communication\InboxController.
 * Backward-compatible default: if a plan has no `unified_inbox` entry in its
 * feature JSON at all, the controller allows access (opt-out model rather
 * than opt-in — v5.0 rollout must not silently disable the inbox on installs
 * that upgraded before plans were reconfigured).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('plan_display_features')) {
            return;
        }

        if (DB::table('plan_display_features')->where('name', 'Unified Inbox')->exists()) {
            return;
        }

        $maxSort = DB::table('plan_display_features')->max('sort_order') ?? 10;

        DB::table('plan_display_features')->insert([
            'uid'         => Str::uuid(),
            'name'        => 'Unified Inbox',
            'icon'        => 'ri-inbox-line',
            'description' => 'One-screen inbox for every incoming reply across SMS, Email, and WhatsApp (Cloud API + Node). Includes cross-channel filter, unread tracking, and search.',
            'sort_order'  => $maxSort + 1,
            'status'      => 'active',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('plan_display_features')) {
            return;
        }
        DB::table('plan_display_features')->where('name', 'Unified Inbox')->delete();
    }
};
