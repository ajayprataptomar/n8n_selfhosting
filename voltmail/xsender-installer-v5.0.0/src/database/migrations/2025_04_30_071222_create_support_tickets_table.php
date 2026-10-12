<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->unsignedInteger('id')->autoIncrement();
            $table->unsignedInteger('user_id')->nullable();
            $table->string('name', 255)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('ticket_number', 50)->nullable();
            $table->string('subject', 255)->nullable();
            $table->tinyInteger('status')->default(1)->comment('Running : 1, Answered : 2, Replied : 3, closed : 4');
            $table->tinyInteger('priority')->default(1)->comment('Low : 1, medium : 2 high: 3');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down()
    {
        Schema::dropIfExists('support_tickets');
    }
};