<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWithdrawLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('withdraw_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('method_id')->index()->nullable();
            $table->unsignedBigInteger('user_id')->index()->nullable();
            $table->string('currency_code', 32);
            $table->string('trx_code',255)->index()->nullable();
            $table->double('amount',25,5)->default(0.00000);
            $table->double('charge',25,5)->default(0.00000);
            $table->double('final_amount',25,5)->default(0.00000);
            $table->longText('custom_data')->nullable();
            $table->enum('status', array_values(\App\Enums\WithdrawLogEnum::toArray()))->default(\App\Enums\WithdrawLogEnum::PENDING->value);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('withdraw_logs');
    }
}
