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
        if (!Schema::hasTable('workflow_templates')) {
            Schema::create('workflow_templates', function (Blueprint $table) {
                $table->id();
                $table->string('uid', 32)->unique();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->string('category');
                $table->string('trigger_type');
                $table->json('trigger_config')->nullable();
                $table->json('nodes');
                $table->string('icon')->default('ri-flow-chart');
                $table->boolean('is_active')->default(true);
                $table->integer('usage_count')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('automation_settings')) {
            Schema::create('automation_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_templates');
        Schema::dropIfExists('automation_settings');
    }
};
