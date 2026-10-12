<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddEmbeddedSignupColumnsToGatewaysTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('gateways', function (Blueprint $table) {
            $table->longText('payload')->nullable()->after('meta_data')->comment('Complete embedded signup response payload');
            $table->string('api_version', 20)->default('v21.0')->after('payload')->comment('WhatsApp API version');
            $table->timestamp('last_sync_at')->nullable()->after('api_version')->comment('Last template sync timestamp');
            $table->enum('setup_method', ['manual', 'embedded'])->default('manual')->after('last_sync_at')->comment('How the gateway was set up');
        });
    }

    public function down()
    {
        Schema::table('gateways', function (Blueprint $table) {
            $table->dropColumn(['payload', 'api_version', 'last_sync_at', 'setup_method']);
        });
    }
}
