<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->autoIncrement();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('template_id')->nullable();
            $table->enum('type', ['email', 'sms', 'whatsapp']);
            $table->string('subject', 255)->nullable();
            $table->longText('main_body')->nullable();
            $table->text('message')->nullable();
            $table->json('meta_data')->nullable();
            $table->json('file_info')->nullable();
            $table->boolean('is_campaign')->default(0);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('messages');
    }
};