<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('sms_gateways', function (Blueprint $table) {
            $table->id();
            $table->string('gateway_code', 30)->nullable();
            $table->string('name', 255)->nullable();
            $table->text('credential');
            $table->tinyInteger('status')->default(1)->comment('Active : 1, Inactive : 2');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('sms_gateways');
    }
};
