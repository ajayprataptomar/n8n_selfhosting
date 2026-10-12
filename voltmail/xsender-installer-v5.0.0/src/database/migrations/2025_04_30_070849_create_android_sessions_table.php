<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up()
    {
        Schema::create('android_sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->autoIncrement();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('name', 255);
            $table->string('qr_code', 255)->nullable();
            $table->string('token', 255)->nullable()->index();
            $table->enum('status', ['initiated', 'connected', 'disconnected', 'expired'])->default('initiated')->index();
            $table->json('meta_data')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }
    public function down()
    {
        Schema::dropIfExists('android_sessions');
    }
};