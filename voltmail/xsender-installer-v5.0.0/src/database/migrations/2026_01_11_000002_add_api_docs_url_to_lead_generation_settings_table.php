<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Skip if table doesn't exist or column already exists
        if (!Schema::hasTable('lead_generation_settings')) {
            return;
        }

        if (Schema::hasColumn('lead_generation_settings', 'api_docs_url')) {
            return;
        }

        Schema::table('lead_generation_settings', function (Blueprint $table) {
            $table->string('api_docs_url')->nullable()->after('google_maps_api_key')
                  ->default('https://developers.google.com/maps/documentation/places/web-service/get-api-key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('lead_generation_settings') && Schema::hasColumn('lead_generation_settings', 'api_docs_url')) {
            Schema::table('lead_generation_settings', function (Blueprint $table) {
                $table->dropColumn('api_docs_url');
            });
        }
    }
};
