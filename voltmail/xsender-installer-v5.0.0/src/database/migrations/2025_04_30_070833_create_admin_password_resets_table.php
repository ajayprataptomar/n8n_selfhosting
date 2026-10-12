<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up()
    {
        Schema::create('admin_password_resets', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->autoIncrement();
            $table->string('email', 255)->nullable();
            $table->string('token', 255);
            $table->timestamps();
        });
    }
    public function down()
    {
        Schema::dropIfExists('admin_password_resets');
    }
};