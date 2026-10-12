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
        if (!Schema::hasTable('campaign_insights')) {
            Schema::create('campaign_insights', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('campaign_id');
                $table->json('hourly_stats')->nullable();
                $table->json('daily_stats')->nullable();
                $table->json('channel_comparison')->nullable();
                $table->json('engagement_heatmap')->nullable();
                $table->enum('trend_direction', ['improving', 'stable', 'declining'])->nullable();
                $table->decimal('delivery_rate', 5, 2)->nullable();
                $table->decimal('open_rate', 5, 2)->nullable();
                $table->decimal('click_rate', 5, 2)->nullable();
                $table->decimal('reply_rate', 5, 2)->nullable();
                $table->decimal('bounce_rate', 5, 2)->nullable();
                $table->json('ai_recommendations')->nullable();
                $table->json('performance_summary')->nullable();
                $table->timestamp('generated_at')->nullable();
                $table->timestamps();

                $table->index('campaign_id');
            });

            // Add foreign key safely
            try {
                Schema::table('campaign_insights', function (Blueprint $table) {
                    $table->foreign('campaign_id')->references('id')->on('unified_campaigns')->onDelete('cascade');
                });
            } catch (\Exception $e) {
                // Foreign key may fail
            }
        }

        if (!Schema::hasTable('content_analyses')) {
            Schema::create('content_analyses', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('campaign_message_id');
                $table->decimal('spam_score', 5, 2)->default(0);
                $table->decimal('deliverability_score', 5, 2)->default(0);
                $table->decimal('subject_score', 5, 2)->nullable();
                $table->decimal('readability_score', 5, 2)->nullable();
                $table->json('spam_triggers')->nullable();
                $table->json('improvements')->nullable();
                $table->json('ai_analysis')->nullable();
                $table->integer('character_count')->default(0);
                $table->integer('word_count')->default(0);
                $table->boolean('has_personalization')->default(false);
                $table->boolean('has_call_to_action')->default(false);
                $table->boolean('has_unsubscribe_link')->default(false);
                $table->timestamps();

                $table->index('campaign_message_id');
            });

            // Add foreign key safely
            try {
                Schema::table('content_analyses', function (Blueprint $table) {
                    $table->foreign('campaign_message_id')->references('id')->on('campaign_messages')->onDelete('cascade');
                });
            } catch (\Exception $e) {
                // Foreign key may fail
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_analyses');
        Schema::dropIfExists('campaign_insights');
    }
};
