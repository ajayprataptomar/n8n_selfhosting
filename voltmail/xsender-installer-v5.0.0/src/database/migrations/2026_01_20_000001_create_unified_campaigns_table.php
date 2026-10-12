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
        if (Schema::hasTable('unified_campaigns')) {
            return;
        }

        Schema::create('unified_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('uid', 100)->unique();
            $table->unsignedInteger('user_id')->nullable();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->enum('status', [
                'draft',
                'scheduled',
                'running',
                'paused',
                'completed',
                'cancelled'
            ])->default('draft')->index();
            $table->enum('type', [
                'instant',
                'scheduled',
                'recurring'
            ])->default('instant');
            $table->timestamp('schedule_at')->nullable()->index();
            $table->string('timezone', 50)->default('UTC');
            $table->json('recurring_config')->nullable();
            $table->unsignedBigInteger('contact_group_id')->nullable();
            $table->json('contact_filter')->nullable();
            $table->json('channels')->comment('Array of channels: sms, email, whatsapp');
            $table->json('channel_priority')->nullable()->comment('Fallback order for channels');
            $table->enum('channel_detection_mode', [
                'auto',
                'manual',
                'priority_fallback'
            ])->default('auto');
            $table->unsignedInteger('total_contacts')->default(0);
            $table->unsignedInteger('processed_contacts')->default(0);
            $table->json('stats')->nullable()->comment('Per-channel delivery statistics');
            $table->json('meta_data')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        // Add foreign key safely
        try {
            Schema::table('unified_campaigns', function (Blueprint $table) {
                $table->foreign('contact_group_id')
                    ->references('id')
                    ->on('contact_groups')
                    ->nullOnDelete();
            });
        } catch (\Exception $e) {
            // Foreign key may fail
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unified_campaigns');
    }
};
