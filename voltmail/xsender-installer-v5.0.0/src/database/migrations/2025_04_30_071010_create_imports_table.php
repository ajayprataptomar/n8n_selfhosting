<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('imports', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->autoIncrement();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('group_id')->nullable();
            $table->string('name', 255)->nullable();
            $table->string('path', 255)->nullable();
            $table->string('mime', 60)->nullable();
            $table->string('type', 60)->nullable();
            $table->tinyInteger('status')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->longText('contact_structure')->nullable();
        });
    }

    public function down()
    {
        Schema::dropIfExists('imports');
    }
};