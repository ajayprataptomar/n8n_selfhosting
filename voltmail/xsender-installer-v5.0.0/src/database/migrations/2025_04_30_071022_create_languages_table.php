<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('languages', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->autoIncrement();
            $table->string('uid', 100)->nullable()->index('languages_uid_index');
            $table->string('name', 191)->unique('languages_name_unique');
            $table->string('code', 100)->nullable();
            $table->enum('is_default', ['0', '1'])->default('1')->comment('Yes: 1, No: 0');
            $table->enum('status', ['0', '1'])->default('1')->comment('Active: 1, Deactive: 0');
            $table->enum('ltr', ['0', '1'])->default('1')->comment('Yes: 1, No: 0');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('languages');
    }
};