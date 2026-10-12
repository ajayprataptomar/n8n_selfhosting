<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('payment_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->autoIncrement();
            $table->integer('subscriptions_id')->nullable();
            $table->integer('user_id')->nullable();
            $table->integer('method_id')->nullable();
            $table->decimal('charge', 18, 8)->default('0.00000000');
            $table->decimal('rate', 18, 8)->default('0.00000000');
            $table->decimal('amount', 18, 8)->default('0.00000000');
            $table->decimal('final_amount', 18, 8)->default('0.00000000');
            $table->string('trx_number', 255);
            $table->text('user_data');
            $table->string('feedback', 255)->nullable();
            $table->tinyInteger('status')->default(0)->comment('Pending: 1, Success: 2, Cancel: 3');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('payment_logs');
    }
};