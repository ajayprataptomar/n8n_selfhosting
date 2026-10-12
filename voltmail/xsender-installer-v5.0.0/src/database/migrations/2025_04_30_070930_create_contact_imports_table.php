<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up()
    {
        Schema::create('contact_imports', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->autoIncrement();
            $table->unsignedBigInteger('file_id')->unique();
            $table->unsignedBigInteger('group_id')->nullable();
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending')->index();
            $table->integer('total_contacts')->default(0);
            $table->integer('processed_contacts')->default(0);
            $table->integer('total_emails')->default(0);
            $table->integer('processed_emails')->default(0);
            $table->json('meta_data')->nullable();
            $table->timestamps();
        });
    }
    public function down()
    {
        Schema::dropIfExists('contact_imports');
    }
};