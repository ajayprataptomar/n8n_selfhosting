<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up()
    {
        Schema::create('android_sims', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->autoIncrement();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('android_session_id')->index();
            $table->string('sim_number', 255);
            $table->boolean('send_sms')->default(1);
            $table->enum('status', ['active', 'inactive'])->default('active')->index();
            $table->json('meta_data')->nullable();
            $table->double('per_message_delay', 8, 2)->default(0.00);
            $table->integer('delay_after_count')->default(0);
            $table->double('delay_after_duration', 8, 2)->default(0.00);
            $table->integer('reset_after_count')->default(0);
            $table->timestamps();
        });
    }
    public function down()
    {
        Schema::dropIfExists('android_sims');
    }
};