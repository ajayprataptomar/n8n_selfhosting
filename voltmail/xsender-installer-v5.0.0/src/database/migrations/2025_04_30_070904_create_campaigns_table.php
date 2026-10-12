<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up()
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->autoIncrement();
            $table->string('uid', 100)->nullable()->index();
            $table->enum('type', ['email', 'sms', 'whatsapp'])->index();
            $table->string('original_type', 255)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('message_id')->nullable()->index();
            $table->unsignedBigInteger('group_id')->nullable()->index();
            $table->string('name', 255);
            $table->enum('priority', ['low', 'medium', 'high'])->default('low')->index();
            $table->integer('repeat_time')->nullable();
            $table->enum('repeat_format', ['none', 'daily', 'weekly', 'monthly', 'yearly'])->default('none');
            $table->string('original_repeat_format', 255)->nullable();
            $table->json('meta_data')->nullable();
            $table->enum('status', ['cancel', 'pending', 'active', 'deactive', 'completed', 'ongoing'])->default('active')->index();
            $table->string('original_status', 255)->nullable();
            $table->dateTime('schedule_at')->nullable();
            $table->timestamps();
        });
    }
    public function down()
    {
        Schema::dropIfExists('campaigns');
    }
};