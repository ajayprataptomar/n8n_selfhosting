<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up()
    {
        Schema::create('blogs', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->autoIncrement();
            $table->string('uid', 32);
            $table->enum('status', ['0', '1'])->comment('Active: 1, Inactive: 0');
            $table->string('title', 255);
            $table->longText('description')->nullable();
            $table->string('image', 255)->nullable();
            $table->json('meta_data')->nullable();
            $table->timestamps();
        });
    }
    public function down()
    {
        Schema::dropIfExists('blogs');
    }
};