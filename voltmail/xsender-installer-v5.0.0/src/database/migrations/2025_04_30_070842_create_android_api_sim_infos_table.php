<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up()
    {
        Schema::create('android_api_sim_infos', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->autoIncrement();
            $table->integer('android_gateway_id')->nullable();
            $table->string('sim_number', 90)->nullable();
            $table->integer('time_interval')->nullable();
            $table->integer('send_sms')->nullable();
            $table->tinyInteger('status')->default(1)->comment('Active: 1, Inactive: 2');
            $table->timestamps();
        });
    }
    public function down()
    {
        Schema::dropIfExists('android_api_sim_infos');
    }
};