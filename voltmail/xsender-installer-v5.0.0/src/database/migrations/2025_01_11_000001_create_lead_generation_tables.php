<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Lead Scraping Jobs table
        if (!Schema::hasTable('lead_scraping_jobs')) {
            Schema::create('lead_scraping_jobs', function (Blueprint $table) {
                $table->id();
                $table->string('uid', 32)->unique();
                $table->unsignedInteger('user_id')->nullable()->index();
                $table->enum('type', ['google_maps', 'website', 'enrichment'])->default('google_maps');
                $table->json('parameters')->nullable();
                $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'cancelled'])->default('pending');
                $table->integer('total_found')->default(0);
                $table->integer('processed_count')->default(0);
                $table->text('error_message')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'status']);
                $table->index(['type', 'status']);
            });
        }

        // Scraped Leads table
        if (!Schema::hasTable('scraped_leads')) {
            Schema::create('scraped_leads', function (Blueprint $table) {
                $table->id();
                $table->string('uid', 32)->unique();
                $table->unsignedBigInteger('job_id')->index();
                $table->unsignedInteger('user_id')->nullable()->index();

                // Business/Contact Information
                $table->string('business_name')->nullable();
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->string('website')->nullable();

                // Location Information
                $table->string('address')->nullable();
                $table->string('city')->nullable();
                $table->string('state')->nullable();
                $table->string('country')->nullable();
                $table->string('postal_code')->nullable();
                $table->decimal('latitude', 10, 8)->nullable();
                $table->decimal('longitude', 11, 8)->nullable();

                // Business Details (for Google Maps)
                $table->string('category')->nullable();
                $table->decimal('rating', 2, 1)->nullable();
                $table->integer('reviews_count')->nullable();
                $table->string('place_id')->nullable();

                // Social Profiles
                $table->string('facebook')->nullable();
                $table->string('instagram')->nullable();
                $table->string('twitter')->nullable();
                $table->string('linkedin')->nullable();

                // Metadata
                $table->string('source_url')->nullable();
                $table->json('raw_data')->nullable();
                $table->integer('quality_score')->default(0);
                $table->boolean('email_verified')->default(false);
                $table->boolean('phone_verified')->default(false);

                // Import tracking
                $table->unsignedBigInteger('imported_to_group_id')->nullable()->index();
                $table->timestamp('imported_at')->nullable();

                $table->timestamps();

                $table->index(['job_id', 'quality_score']);
                $table->index(['user_id', 'imported_to_group_id']);
                $table->index('email');
                $table->index('phone');
                $table->index('place_id');
            });
        }

        // Lead Generation Settings
        if (!Schema::hasTable('lead_generation_settings')) {
            Schema::create('lead_generation_settings', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('user_id')->nullable()->unique();
                $table->string('google_maps_api_key')->nullable();
                $table->integer('daily_scrape_limit')->default(100);
                $table->integer('monthly_scrape_limit')->default(2000);
                $table->integer('scrapes_today')->default(0);
                $table->integer('scrapes_this_month')->default(0);
                $table->date('last_daily_reset')->nullable();
                $table->date('last_monthly_reset')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('lead_generation_settings');
        Schema::dropIfExists('scraped_leads');
        Schema::dropIfExists('lead_scraping_jobs');
    }
};
