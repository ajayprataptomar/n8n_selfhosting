<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('plan_display_features')) {
            return;
        }

        $maxSort = DB::table('plan_display_features')->max('sort_order') ?? 10;

        $features = [
            [
                'name' => 'DKIM Email Authentication',
                'icon' => 'ri-shield-keyhole-line',
                'description' => 'Cryptographic DKIM signing for every outgoing email with sending domain verification, SPF & DMARC guidance',
                'sort_order' => $maxSort + 1,
            ],
            [
                'name' => 'Email Open & Click Tracking',
                'icon' => 'ri-eye-line',
                'description' => 'Real-time tracking of email opens and link clicks with per-campaign analytics',
                'sort_order' => $maxSort + 2,
            ],
            [
                'name' => 'Bounce & Suppression Management',
                'icon' => 'ri-spam-line',
                'description' => 'Automatic bounce detection, address suppression, and spam complaint handling to protect sender reputation',
                'sort_order' => $maxSort + 3,
            ],
            [
                'name' => 'Custom Tracking Domains',
                'icon' => 'ri-link',
                'description' => 'Branded tracking URLs with custom CNAME domains for improved deliverability and professional appearance',
                'sort_order' => $maxSort + 4,
            ],
        ];

        foreach ($features as $feature) {
            // Skip if a feature with the same name already exists
            if (DB::table('plan_display_features')->where('name', $feature['name'])->exists()) {
                continue;
            }

            DB::table('plan_display_features')->insert([
                'uid' => Str::uuid(),
                'name' => $feature['name'],
                'icon' => $feature['icon'],
                'description' => $feature['description'],
                'sort_order' => $feature['sort_order'],
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('plan_display_features')) {
            return;
        }

        DB::table('plan_display_features')->whereIn('name', [
            'DKIM Email Authentication',
            'Email Open & Click Tracking',
            'Bounce & Suppression Management',
            'Custom Tracking Domains',
        ])->delete();
    }
};
