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
        if (Schema::hasTable('campaign_messages')) {
            return;
        }

        Schema::create('campaign_messages', function (Blueprint $table) {
            $table->id();
            $table->string('uid', 100)->unique();
            $table->unsignedBigInteger('campaign_id');
            $table->enum('channel', ['sms', 'email', 'whatsapp']);
            $table->unsignedBigInteger('gateway_id')->nullable();
            $table->string('subject', 500)->nullable()->comment('Email subject line');
            $table->longText('content');
            $table->unsignedBigInteger('template_id')->nullable();
            $table->json('attachments')->nullable();
            $table->json('personalization_vars')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('meta_data')->nullable();
            $table->timestamps();

            $table->unique(['campaign_id', 'channel']);
        });

        // Add foreign keys safely
        try {
            Schema::table('campaign_messages', function (Blueprint $table) {
                $table->foreign('campaign_id')
                    ->references('id')
                    ->on('unified_campaigns')
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
        Schema::dropIfExists('campaign_messages');
    }
};
