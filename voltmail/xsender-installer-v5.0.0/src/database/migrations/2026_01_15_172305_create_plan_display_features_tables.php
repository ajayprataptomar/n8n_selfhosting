<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Global plan display features (marketing text labels)
        if (!Schema::hasTable('plan_display_features')) {
            Schema::create('plan_display_features', function (Blueprint $table) {
                $table->id();
                $table->string('uid', 36)->unique();
                $table->string('name');
                $table->string('icon')->nullable();
                $table->text('description')->nullable();
                $table->integer('sort_order')->default(0);
                $table->enum('status', ['active', 'inactive'])->default('active');
                $table->timestamps();
            });

            // Insert default features
            $defaultFeatures = [
                ['name' => 'SMS Messaging', 'icon' => 'ri-message-2-line', 'sort_order' => 1],
                ['name' => 'Email Marketing', 'icon' => 'ri-mail-line', 'sort_order' => 2],
                ['name' => 'WhatsApp Messaging', 'icon' => 'ri-whatsapp-line', 'sort_order' => 3],
                ['name' => 'Contact Management', 'icon' => 'ri-contacts-line', 'sort_order' => 4],
                ['name' => 'Campaign Scheduling', 'icon' => 'ri-calendar-line', 'sort_order' => 5],
                ['name' => 'Lead Generation', 'icon' => 'ri-search-eye-line', 'sort_order' => 6],
                ['name' => 'Workflow Automation', 'icon' => 'ri-flow-chart', 'sort_order' => 7],
                ['name' => 'Analytics & Reports', 'icon' => 'ri-bar-chart-line', 'sort_order' => 8],
                ['name' => 'API Access', 'icon' => 'ri-code-s-slash-line', 'sort_order' => 9],
                ['name' => 'Priority Support', 'icon' => 'ri-customer-service-2-line', 'sort_order' => 10],
            ];

            foreach ($defaultFeatures as $feature) {
                DB::table('plan_display_features')->insert([
                    'uid' => Str::uuid(),
                    'name' => $feature['name'],
                    'icon' => $feature['icon'],
                    'sort_order' => $feature['sort_order'],
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Pivot table: which features are included in which plan
        if (!Schema::hasTable('plan_feature_assignments')) {
            Schema::create('plan_feature_assignments', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('pricing_plan_id');
                $table->unsignedBigInteger('plan_display_feature_id');
                $table->boolean('is_included')->default(true);
                $table->string('custom_text')->nullable();
                $table->timestamps();

                $table->unique(['pricing_plan_id', 'plan_display_feature_id'], 'plan_feature_unique');
            });

            // Add foreign keys only if they don't exist (safer for updates)
            try {
                Schema::table('plan_feature_assignments', function (Blueprint $table) {
                    $table->foreign('pricing_plan_id')->references('id')->on('pricing_plans')->onDelete('cascade');
                    $table->foreign('plan_display_feature_id')->references('id')->on('plan_display_features')->onDelete('cascade');
                });
            } catch (\Exception $e) {
                // Foreign keys may already exist or table structure differs
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plan_feature_assignments');
        Schema::dropIfExists('plan_display_features');
    }
};
