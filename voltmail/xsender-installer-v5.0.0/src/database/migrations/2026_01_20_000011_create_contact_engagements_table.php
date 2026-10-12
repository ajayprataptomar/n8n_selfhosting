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
        if (Schema::hasTable('contact_engagements')) {
            return;
        }

        Schema::create('contact_engagements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contact_id');
            $table->unsignedInteger('user_id')->nullable();
            $table->enum('channel', ['sms', 'email', 'whatsapp']);
            $table->decimal('engagement_score', 5, 2)->default(0);
            $table->string('detected_timezone', 50)->nullable();
            $table->json('optimal_hours')->nullable();
            $table->json('optimal_days')->nullable();
            $table->integer('total_sent')->default(0);
            $table->integer('total_delivered')->default(0);
            $table->integer('total_opened')->default(0);
            $table->integer('total_clicked')->default(0);
            $table->integer('total_replied')->default(0);
            $table->integer('total_bounced')->default(0);
            $table->timestamp('last_engagement_at')->nullable();
            $table->timestamp('analyzed_at')->nullable();
            $table->timestamps();

            $table->unique(['contact_id', 'channel']);
            $table->index(['user_id', 'channel']);
            $table->index('engagement_score');
        });

        // Add foreign key safely
        try {
            Schema::table('contact_engagements', function (Blueprint $table) {
                $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('cascade');
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
        Schema::dropIfExists('contact_engagements');
    }
};
