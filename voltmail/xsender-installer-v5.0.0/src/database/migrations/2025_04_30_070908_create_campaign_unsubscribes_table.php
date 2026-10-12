<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up()
    {
        Schema::create('campaign_unsubscribes', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->autoIncrement();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('contact_uid', 255);
            $table->unsignedBigInteger('campaign_id');
            $table->enum('channel', ['1', '2', '3'])->comment('SMS: 1, WhatsApp: 2, Email: 3');
            $table->json('meta_data')->nullable();
            $table->timestamps();
            $table->index(['campaign_id', 'contact_uid', 'channel']);
        });
    }
    public function down()
    {
        Schema::dropIfExists('campaign_unsubscribes');
    }
};