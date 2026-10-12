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
        if (Schema::hasTable('campaign_dispatches')) {
            return;
        }

        Schema::create('campaign_dispatches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campaign_id')->index();
            $table->unsignedBigInteger('campaign_message_id')->nullable();
            $table->unsignedBigInteger('contact_id')->index();
            $table->enum('channel', ['sms', 'email', 'whatsapp'])->index();
            $table->unsignedBigInteger('gateway_id')->nullable();
            $table->enum('status', [
                'pending',
                'queued',
                'processing',
                'sent',
                'delivered',
                'failed',
                'bounced',
                'opened',
                'clicked',
                'replied'
            ])->default('pending')->index();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedTinyInteger('retry_count')->default(0);
            $table->json('meta_data')->nullable();
            $table->timestamps();

            // Composite indexes for efficient queries
            $table->index(['campaign_id', 'status']);
            $table->index(['campaign_id', 'channel', 'status']);
        });

        // Add foreign keys safely
        try {
            Schema::table('campaign_dispatches', function (Blueprint $table) {
                $table->foreign('campaign_id')
                    ->references('id')
                    ->on('unified_campaigns')
                    ->cascadeOnDelete();

                $table->foreign('campaign_message_id')
                    ->references('id')
                    ->on('campaign_messages')
                    ->nullOnDelete();

                $table->foreign('contact_id')
                    ->references('id')
                    ->on('contacts')
                    ->cascadeOnDelete();

                $table->foreign('gateway_id')
                    ->references('id')
                    ->on('gateways')
                    ->nullOnDelete();
            });
        } catch (\Exception $e) {
            // Foreign keys may fail
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaign_dispatches');
    }
};
