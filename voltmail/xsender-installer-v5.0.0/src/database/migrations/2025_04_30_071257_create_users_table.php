<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('uid', 32)->index();
            $table->string('name', 255);
            $table->string('email', 70)->unique();
            $table->string('google_id', 255)->nullable();
            $table->integer('sms_credit')->default(0)->comment('Allocated by current Plan');
            $table->integer('email_credit')->default(0)->comment('Allocated by current Plan');
            $table->string('whatsapp_credit', 299)->default('0')->comment('Allocated by current Plan');
            $table->enum('api_sms_method', ['0', '1'])->default('1')->comment('SMS API Gateway: 0, Android Gateway: 1');
            $table->string('webhook_token', 255)->default('###');
            $table->text('contact_meta_data')->nullable();
            $table->text('address')->nullable();
            $table->string('image', 191)->nullable();
            $table->string('password', 255)->nullable();
            $table->enum('status', ['0', '1'])->default('1')->comment('Active: 1, Banned: 0');
            $table->string('api_key', 255)->nullable();
            $table->json('gateway_credentials')->nullable();
            $table->timestamp('email_verified_send_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->enum('email_verified_status', ['0', '1'])->default('0')->comment('YES: 1, NO: 0');
            $table->string('email_verified_code', 50)->nullable();
            $table->timestamp('email_verified_at')->nullable();
        });
    }

    public function down()
    {
        Schema::dropIfExists('users');
    }
};