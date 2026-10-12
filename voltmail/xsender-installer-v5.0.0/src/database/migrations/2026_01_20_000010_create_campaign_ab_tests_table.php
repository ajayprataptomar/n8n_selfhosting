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
        if (!Schema::hasTable('campaign_ab_tests')) {
            Schema::create('campaign_ab_tests', function (Blueprint $table) {
                $table->id();
                $table->string('uid', 100)->unique();
                $table->unsignedBigInteger('campaign_id');
                $table->string('name');
                $table->enum('status', ['draft', 'running', 'paused', 'completed', 'winner_selected'])->default('draft');
                $table->integer('test_percentage')->default(20);
                $table->enum('winning_metric', ['delivered', 'opened', 'clicked', 'replied'])->default('delivered');
                $table->decimal('confidence_level', 3, 2)->default(0.95);
                $table->unsignedBigInteger('winning_variant_id')->nullable();
                $table->timestamp('winner_selected_at')->nullable();
                $table->boolean('auto_select_winner')->default(true);
                $table->integer('test_duration_hours')->default(24);
                $table->json('meta_data')->nullable();
                $table->timestamps();

                $table->index(['campaign_id', 'status']);
            });

            // Add foreign key safely
            try {
                Schema::table('campaign_ab_tests', function (Blueprint $table) {
                    $table->foreign('campaign_id')->references('id')->on('unified_campaigns')->onDelete('cascade');
                });
            } catch (\Exception $e) {
                // Foreign key may fail
            }
        }

        if (!Schema::hasTable('campaign_ab_variants')) {
            Schema::create('campaign_ab_variants', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('ab_test_id');
                $table->char('variant_label', 1);
                $table->unsignedBigInteger('campaign_message_id');
                $table->integer('contact_count')->default(0);
                $table->integer('sent_count')->default(0);
                $table->integer('delivered_count')->default(0);
                $table->integer('opened_count')->default(0);
                $table->integer('clicked_count')->default(0);
                $table->integer('replied_count')->default(0);
                $table->boolean('is_winner')->default(false);
                $table->json('meta_data')->nullable();
                $table->timestamps();

                $table->unique(['ab_test_id', 'variant_label']);
            });

            // Add foreign keys safely
            try {
                Schema::table('campaign_ab_variants', function (Blueprint $table) {
                    $table->foreign('ab_test_id')->references('id')->on('campaign_ab_tests')->onDelete('cascade');
                    $table->foreign('campaign_message_id')->references('id')->on('campaign_messages')->onDelete('cascade');
                });
            } catch (\Exception $e) {
                // Foreign keys may fail
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaign_ab_variants');
        Schema::dropIfExists('campaign_ab_tests');
    }
};
