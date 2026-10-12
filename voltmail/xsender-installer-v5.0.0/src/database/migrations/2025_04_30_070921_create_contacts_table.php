<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up()
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->autoIncrement();
            $table->string('uid', 32)->nullable()->index();
            $table->unsignedInteger('user_id')->nullable();
            $table->unsignedInteger('group_id')->nullable();
            $table->longText('meta_data')->nullable();
            $table->string('whatsapp_contact', 50)->nullable();
            $table->string('email_contact', 120)->nullable();
            $table->string('sms_contact', 50)->nullable();
            $table->string('last_name', 90)->nullable();
            $table->string('first_name', 90)->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->enum('email_verification', ['verified', 'unverified'])->nullable();
            $table->timestamps();
        });
    }
    public function down()
    {
        Schema::dropIfExists('contacts');
    }
};