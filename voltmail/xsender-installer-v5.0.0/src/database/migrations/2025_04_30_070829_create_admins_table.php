<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up()
    {
        Schema::create('admins', function (Blueprint $table) {
            $table->unsignedInteger('id')->autoIncrement();
            $table->string('name', 70)->nullable();
            $table->string('username', 70)->unique()->nullable();
            $table->string('email', 70)->unique()->nullable();
            $table->string('image', 120)->nullable();
            $table->string('password', 255)->nullable();
            $table->string('api_key', 255)->nullable();
            $table->timestamps();
        });
    }
    public function down()
    {
        Schema::dropIfExists('admins');
    }
};