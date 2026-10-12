<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
     public function up()
     {
          DB::statement('ALTER TABLE android_sessions MODIFY COLUMN qr_code TEXT');
     }

     public function down()
     {
          // Revert back to string if needed
          Schema::table('android_sessions', function (Blueprint $table) {
               $table->string('qr_code', 255)->nullable()->change();
          });
     }
};
