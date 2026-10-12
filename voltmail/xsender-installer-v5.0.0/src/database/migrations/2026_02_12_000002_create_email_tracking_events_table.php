<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('email_tracking_events')) {
            return;
        }

        Schema::create('email_tracking_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('dispatch_log_id');
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->unsignedBigInteger('campaign_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->enum('event_type', ['open', 'click']);
            $table->string('url', 2048)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('dispatch_log_id');
            $table->index('contact_id');
            $table->index('campaign_id');
            $table->index('user_id');
            $table->index('event_type');
            $table->index('created_at');

            $table->foreign('dispatch_log_id')
                ->references('id')
                ->on('dispatch_logs')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_tracking_events');
    }
};
