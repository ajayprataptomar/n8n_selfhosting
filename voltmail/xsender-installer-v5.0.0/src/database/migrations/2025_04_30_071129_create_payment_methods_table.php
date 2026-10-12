<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->autoIncrement();
            $table->string('currency_code', 32);
            $table->decimal('percent_charge', 18, 8)->nullable();
            $table->decimal('rate', 18, 8)->nullable();
            $table->string('name', 255)->nullable();
            $table->string('unique_code', 50)->nullable()->unique('payment_methods_unique_code_unique');
            $table->string('image', 255)->nullable();
            $table->text('payment_parameter');
            $table->enum('status', ['0', '1'])->default('1')->comment('Active: 1, Banned: 0');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('payment_methods');
    }
};