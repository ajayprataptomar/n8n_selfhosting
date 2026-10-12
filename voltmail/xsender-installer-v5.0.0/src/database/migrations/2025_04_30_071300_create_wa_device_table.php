<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('wa_device', function (Blueprint $table) {
            $table->integer('id')->autoIncrement();
            $table->string('uid', 32)->nullable()->index();
            $table->integer('user_id')->nullable();
            $table->integer('admin_id')->nullable();
            $table->tinyInteger('type')->nullable()->comment('Whatsapp Node module: 0, Whatsapp Business API: 1');
            $table->longText('credentials')->nullable();
            $table->string('name', 259);
            $table->enum('status', ['connected', 'disconnected', 'initiate']);
            $table->datetime('created_at');
            $table->datetime('updated_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('wa_device');
    }
};