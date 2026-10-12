<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('post_webhook_logs', function (Blueprint $table) {
            $table->string('webhook_type')->nullable()->after('user_id'); // e.g., 'message_received', 'status_update'
            $table->string('whatsapp_message_id')->nullable()->after('webhook_type');
            $table->boolean('processed')->default(false)->after('whatsapp_message_id');
            $table->text('processing_error')->nullable()->after('processed');
        });
    }

    public function down(): void
    {
        Schema::table('post_webhook_logs', function (Blueprint $table) {
            $table->dropColumn(['webhook_type', 'whatsapp_message_id', 'processed', 'processing_error']);
        });
    }
};

