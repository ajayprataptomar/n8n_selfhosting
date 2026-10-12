<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('b2b_providers')) {
            Schema::create('b2b_providers', function (Blueprint $table) {
                $table->id();
                $table->string('uid', 50)->unique();
                // users.id is INT UNSIGNED (legacy schema uses $table->increments('id')),
                // so user_id must match exactly or MySQL rejects the FK with errno 3780.
                $table->unsignedInteger('user_id')->nullable()->index();
                $table->string('type', 30)->index();
                $table->string('name', 100)->nullable();
                $table->text('meta_data');
                $table->boolean('is_active')->default(true);
                $table->timestamp('last_synced_at')->nullable();
                $table->unsignedInteger('total_lookups')->default(0);
                $table->unsignedInteger('monthly_lookups')->default(0);
                $table->date('last_monthly_reset')->nullable();
                $table->enum('status', ['active', 'inactive'])->default('active');
                $table->timestamps();

                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }

        if (!Schema::hasTable('b2b_search_logs')) {
            Schema::create('b2b_search_logs', function (Blueprint $table) {
                $table->id();
                $table->string('uid', 50)->unique();
                // Match users.id type (see note in b2b_providers above).
                $table->unsignedInteger('user_id')->index();
                // b2b_providers.id is BIGINT UNSIGNED ($table->id()), so this stays unsignedBigInteger.
                $table->unsignedBigInteger('provider_id')->index();
                $table->string('provider_type', 30);
                $table->json('search_filters');
                $table->unsignedInteger('total_results')->default(0);
                $table->unsignedInteger('imported_count')->default(0);
                $table->unsignedInteger('credits_used')->default(0);
                $table->unsignedBigInteger('imported_to_group_id')->nullable();
                $table->enum('status', ['completed', 'failed', 'partial', 'pending'])->default('pending');
                $table->text('error_message')->nullable();
                $table->timestamps();

                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('provider_id')->references('id')->on('b2b_providers')->cascadeOnDelete();
            });
        }

        // Extend lead_generation_settings if the table exists
        if (Schema::hasTable('lead_generation_settings')) {
            if (!Schema::hasColumn('lead_generation_settings', 'b2b_lookups_today')) {
                Schema::table('lead_generation_settings', function (Blueprint $table) {
                    $table->unsignedInteger('b2b_lookups_today')->default(0)->after('monthly_scrape_limit');
                    $table->unsignedInteger('b2b_lookups_this_month')->default(0)->after('b2b_lookups_today');
                    $table->unsignedInteger('b2b_daily_limit')->default(100)->after('b2b_lookups_this_month');
                    $table->unsignedInteger('b2b_monthly_limit')->default(500)->after('b2b_daily_limit');
                });
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('b2b_search_logs');
        Schema::dropIfExists('b2b_providers');
    }
};
