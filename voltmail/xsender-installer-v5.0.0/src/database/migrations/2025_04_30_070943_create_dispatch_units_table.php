<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('dispatch_units', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->autoIncrement();
            $table->unsignedBigInteger('gateway_id')->index('dispatch_units_gateway_id_index');
            $table->unsignedBigInteger('message_id')->index('dispatch_units_message_id_index');
            $table->enum('type', ['email', 'sms', 'whatsapp'])->index('dispatch_units_type_index');
            $table->integer('log_count')->default(0);
            $table->enum('status', ['cancel', 'pending', 'schedule', 'fail', 'delivered', 'processing'])->default('pending')->index('dispatch_units_status_index');
            $table->text('response_message')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('dispatch_units');
    }
};