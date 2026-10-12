<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('gateways', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->autoIncrement();
            $table->string('uid', 32)->nullable()->index('gateways_uid_index');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('type', 255)->nullable();
            $table->enum('channel', ['email', 'sms', 'whatsapp'])->index('gateways_channel_index');
            $table->json('mail_gateways')->nullable();
            $table->json('sms_gateways')->nullable();
            $table->string('name', 255)->nullable();
            $table->string('address', 255)->nullable();
            $table->json('meta_data')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active')->index('gateways_status_index');
            $table->string('original_status', 255)->nullable();
            $table->tinyInteger('is_default')->default(0);
            $table->integer('bulk_contact_limit')->default(1);
            $table->unsignedBigInteger('sent_message_count')->nullable();
            $table->double('per_message_min_delay', 8, 2)->default(0.00);
            $table->double('per_message_max_delay', 8, 2)->default(0.00);
            $table->integer('delay_after_count')->default(0);
            $table->double('delay_after_duration', 8, 2)->default(0.00);
            $table->integer('reset_after_count')->default(0);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('gateways');
    }
};