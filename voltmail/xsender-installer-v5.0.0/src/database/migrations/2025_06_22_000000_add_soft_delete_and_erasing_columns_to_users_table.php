<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('deleted_at')->nullable()->after('updated_at');
            $table->boolean('is_erasing')->default(false)->after('deleted_at');
            $table->integer('total_entries')->default(0)->after('is_erasing');
            $table->integer('total_deleted_entries')->default(0)->after('total_entries');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['deleted_at', 'is_erasing', 'total_entries', 'total_deleted_entries']);
        });
    }
};
