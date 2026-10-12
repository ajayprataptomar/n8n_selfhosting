<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('dispatch_delays', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('gateway_id')->nullable()->index();
            $table->enum('channel', ['sms', 'email', 'whatsapp'])->index();
            $table->unsignedBigInteger('dispatch_id')->nullable()->index(); 
            $table->enum('dispatch_type', ['regular', 'campaign'])->index();
            $table->double('delay_value', 8, 2)->default(0.00);
            $table->timestamp('applies_from')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('dispatch_delays');
    }
};
