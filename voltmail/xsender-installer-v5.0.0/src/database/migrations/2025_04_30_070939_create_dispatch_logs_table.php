<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('dispatch_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->autoIncrement();
            $table->unsignedBigInteger('user_id')->nullable()->index('dispatch_logs_user_id_index');
            $table->unsignedBigInteger('message_id')->nullable()->index('dispatch_logs_message_id_index');
            $table->unsignedBigInteger('contact_id')->nullable()->index('dispatch_logs_contact_id_index');
            $table->unsignedBigInteger('campaign_id')->nullable()->index('dispatch_logs_campaign_id_index');
            $table->unsignedBigInteger('dispatch_unit_id')->nullable()->index('dispatch_logs_dispatch_unit_id_index');
            $table->enum('type', ['email', 'sms', 'whatsapp'])->index('dispatch_logs_type_index');
            $table->unsignedBigInteger('gatewayable_id')->nullable()->index('dispatch_logs_gatewayable_id_index');
            $table->string('gatewayable_type', 255)->nullable()->index('dispatch_logs_gatewayable_type_index');
            $table->enum('priority', ['low', 'medium', 'high'])->default('low')->index('dispatch_logs_priority_index');
            $table->enum('status', ['cancel', 'pending', 'schedule', 'fail', 'delivered', 'processing'])->default('pending')->index('dispatch_logs_status_index');
            $table->text('response_message')->nullable();
            $table->json('meta_data')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->double('applied_delay', 8, 2)->default(0.00);
            $table->tinyInteger('retry_count')->default(0);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('dispatch_logs');
    }
};