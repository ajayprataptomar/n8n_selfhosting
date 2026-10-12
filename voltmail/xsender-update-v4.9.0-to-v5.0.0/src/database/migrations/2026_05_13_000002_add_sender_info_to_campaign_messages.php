<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds From Name and Reply-To fields to campaign_messages so campaign senders
 * can override the gateway's default display name and reply address per message.
 * Both nullable — null falls back to the gateway's configured values.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('campaign_messages')) {
            return;
        }

        Schema::table('campaign_messages', function (Blueprint $table) {
            if (!Schema::hasColumn('campaign_messages', 'from_name')) {
                $table->string('from_name', 150)->nullable()->after('subject');
            }
            if (!Schema::hasColumn('campaign_messages', 'reply_to')) {
                $table->string('reply_to', 191)->nullable()->after('from_name');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('campaign_messages')) {
            return;
        }

        Schema::table('campaign_messages', function (Blueprint $table) {
            if (Schema::hasColumn('campaign_messages', 'reply_to')) {
                $table->dropColumn('reply_to');
            }
            if (Schema::hasColumn('campaign_messages', 'from_name')) {
                $table->dropColumn('from_name');
            }
        });
    }
};
