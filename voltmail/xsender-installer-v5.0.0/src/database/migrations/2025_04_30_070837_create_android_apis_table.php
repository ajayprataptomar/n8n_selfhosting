<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up()
    {
        Schema::create('android_apis', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->autoIncrement();
            $table->integer('admin_id')->nullable();
            $table->integer('user_id')->nullable();
            $table->string('name', 255)->nullable();
            $table->string('show_password', 255)->nullable();
            $table->string('password', 255)->nullable();
            $table->tinyInteger('status')->default(1)->comment('Active: 1, Inactive: 2');
            $table->timestamps();
        });
    }
    public function down()
    {
        Schema::dropIfExists('android_apis');
    }
};