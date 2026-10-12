<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('warmup_schedules', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->autoIncrement();
            $table->string('uid', 32)->nullable()->index('warmup_schedules_uid_index');
            // users.id is INT UNSIGNED ($table->increments('id')), so user_id must be unsignedInteger
            $table->unsignedInteger('user_id')->index('warmup_schedules_user_id_index');
            $table->unsignedBigInteger('gateway_id')->index('warmup_schedules_gateway_id_index');
            $table->string('status', 32)->default('active')->index('warmup_schedules_status_index');
            $table->unsignedInteger('daily_limit')->default(40);
            $table->unsignedInteger('rampup_increment')->default(2);
            $table->unsignedInteger('current_day')->default(1);
            $table->unsignedInteger('current_daily_quota')->default(5);
            $table->unsignedInteger('reply_rate_percent')->default(30);
            $table->date('start_date')->nullable();
            $table->timestamp('last_dispatched_at')->nullable();
            $table->json('imap_watermark')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('gateway_id')->references('id')->on('gateways')->onDelete('cascade');

            // Unique constraint: one warmup schedule per mailbox gateway per user
            $table->unique(['user_id', 'gateway_id'], 'user_gateway_warmup_unique');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('warmup_schedules');
    }
};
