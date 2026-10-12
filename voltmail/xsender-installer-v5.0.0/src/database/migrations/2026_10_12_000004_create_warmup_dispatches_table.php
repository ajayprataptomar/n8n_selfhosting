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
        Schema::create('warmup_dispatches', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->autoIncrement();
            $table->string('uid', 32)->nullable()->index('warmup_dispatches_uid_index');
            $table->unsignedBigInteger('warmup_schedule_id')->index('warmup_dispatches_schedule_id_index');
            // users.id is INT UNSIGNED ($table->increments('id')), so user_id must be unsignedInteger
            $table->unsignedInteger('user_id')->index('warmup_dispatches_user_id_index');
            $table->unsignedBigInteger('gateway_id')->index('warmup_dispatches_gateway_id_index');
            $table->unsignedBigInteger('content_resource_id')->nullable()->index('warmup_dispatches_content_id_index');
            $table->string('recipient_email', 255);
            $table->string('subject', 255);
            $table->string('message_id', 255)->nullable()->index('warmup_dispatches_message_id_index');
            $table->date('dispatch_date')->index('warmup_dispatches_date_index');
            $table->unsignedInteger('slot_number');
            $table->timestamp('scheduled_at')->nullable()->index('warmup_dispatches_sched_at_index');
            $table->timestamp('claimed_at')->nullable()->index('warmup_dispatches_claimed_at_index');
            $table->string('claimed_by', 64)->nullable();
            $table->string('status', 32)->default('pending')->index('warmup_dispatches_status_index');
            $table->string('response_code', 32)->nullable();
            $table->text('response_text')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->string('inbox_placement', 32)->nullable(); // inbox, spam, unknown
            $table->json('meta_data')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('warmup_schedule_id')->references('id')->on('warmup_schedules')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('gateway_id')->references('id')->on('gateways')->onDelete('cascade');

            // Unique slot reservation constraint to guarantee idempotency and prevent duplicate emails
            $table->unique(['warmup_schedule_id', 'dispatch_date', 'slot_number'], 'schedule_date_slot_unique');

            // High-throughput composite index for outbox claiming and stale lease recovery
            $table->index(['status', 'scheduled_at'], 'warmup_dispatches_status_sched_idx');
            $table->index(['status', 'claimed_at'], 'warmup_dispatches_status_claimed_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('warmup_dispatches');
    }
};
