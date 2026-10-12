<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up()
    {
        Schema::create('migration_errors', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->autoIncrement();
            $table->unsignedBigInteger('import_id');
            $table->string('uid', 255);
            $table->json('meta_data')->nullable();
            $table->timestamps();
        });
    }
    public function down()
    {
        Schema::dropIfExists('migration_errors');
    }
};