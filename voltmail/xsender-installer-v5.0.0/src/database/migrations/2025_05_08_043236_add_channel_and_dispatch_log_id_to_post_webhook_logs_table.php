<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Enums\System\ChannelTypeEnum;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('post_webhook_logs', 'channel')) {
            Schema::table('post_webhook_logs', function (Blueprint $table) {
                $table->enum('channel', ChannelTypeEnum::getValues())->nullable()->after('user_id');
                $table->unsignedBigInteger('dispatch_log_id')->nullable()->after('channel');
            });
        }
    }

    public function down(): void
    {
        Schema::table('post_webhook_logs', function (Blueprint $table) {
            $table->dropColumn(['channel', 'dispatch_log_id']);
        });
    }
};