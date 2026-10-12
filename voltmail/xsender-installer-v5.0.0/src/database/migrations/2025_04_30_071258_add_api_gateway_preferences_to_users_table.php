<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Skip if users table doesn't exist
        if (!Schema::hasTable('users')) {
            return;
        }

        // Skip if columns already exist
        if (Schema::hasColumn('users', 'api_whatsapp_gateway_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            // API Gateway preferences for each channel
            // null = automatic, -1 = random from available, specific ID = use that gateway
            $table->string('api_whatsapp_gateway_id', 20)->nullable()->after('api_sms_method');
            $table->string('api_sms_gateway_id', 20)->nullable()->after('api_whatsapp_gateway_id');
            $table->string('api_email_gateway_id', 20)->nullable()->after('api_sms_gateway_id');
        });
    }

    public function down()
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        $columns = ['api_whatsapp_gateway_id', 'api_sms_gateway_id', 'api_email_gateway_id'];
        $existingColumns = array_filter($columns, function ($col) {
            return Schema::hasColumn('users', $col);
        });

        if (!empty($existingColumns)) {
            Schema::table('users', function (Blueprint $table) use ($existingColumns) {
                $table->dropColumn($existingColumns);
            });
        }
    }
};
