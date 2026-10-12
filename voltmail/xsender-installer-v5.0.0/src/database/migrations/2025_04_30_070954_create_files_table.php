<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('files', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->autoIncrement();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('fileable_type', 255);
            $table->unsignedBigInteger('fileable_id');
            $table->string('path', 255);
            $table->string('name', 255);
            $table->string('mime_type', 255);
            $table->integer('size');
            $table->json('meta_data')->nullable();
            $table->timestamps();

            $table->index(['fileable_type', 'fileable_id'], 'files_fileable_type_fileable_id_index');
        });
    }

    public function down()
    {
        Schema::dropIfExists('files');
    }
};