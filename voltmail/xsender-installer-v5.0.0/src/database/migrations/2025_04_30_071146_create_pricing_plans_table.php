<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('pricing_plans', function (Blueprint $table) {
            $table->unsignedInteger('id')->autoIncrement();
            $table->string('name', 255)->nullable();
            $table->enum('type', ['0', '1'])->nullable()->comment('Admin: 1, User: 0');
            $table->enum('carry_forward', ['0', '1'])->nullable()->comment('Enable: 1, Disable: 0');
            $table->longText('whatsapp')->nullable()->comment('Whatsapp Information');
            $table->longText('email')->nullable()->comment('Email Information');
            $table->longText('sms')->nullable()->comment('SMS Information');
            $table->decimal('amount', 18, 8)->default('0.00000000');
            $table->integer('duration')->nullable();
            $table->enum('status', ['0', '1'])->default('1')->comment('Active: 1, Inactive: 0');
            $table->enum('recommended_status', ['0', '1'])->default('0')->comment('Active: 1, Inactive: 0');
            $table->timestamps();
            $table->text('description')->nullable();
        });
    }

    public function down()
    {
        Schema::dropIfExists('pricing_plans');
    }
};