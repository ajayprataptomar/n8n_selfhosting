<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWithdrawMethodsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('withdraw_methods', function (Blueprint $table) {

            $table->unsignedBigInteger('id')->autoIncrement();
            $table->string('uid',100)->index()->nullable();
            $table->string('currency_code', 32);
            $table->string('name', 255)->nullable();
            $table->longtext('duration');
            $table->double('minimum_amount',25,5)->default(0.00000);
            $table->double('maximum_amount',25,5)->default(0.00000);
            $table->double('fixed_charge',25,5)->default(0.00000);
            $table->double('percent_charge',25,5)->default(0.00000);
            $table->text('note')->nullable();
            $table->string('image', 255)->nullable();
            $table->longText('parameters')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active')->index('withdraw_methods_status_index');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('with_draw_methods');
    }
}
