<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bounce_logs')) {
            return;
        }

        Schema::create('bounce_logs', function (Blueprint $table) {
            $table->id();
            $table->string('uid', 100)->unique();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('dispatch_log_id')->nullable()->index();
            $table->string('email_address', 255)->index();
            $table->enum('bounce_type', ['hard', 'soft', 'complaint'])->index();
            $table->string('bounce_code', 20)->nullable();
            $table->text('bounce_message')->nullable();
            $table->string('provider', 50)->nullable();
            $table->boolean('processed')->default(false)->index();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->foreign('dispatch_log_id')
                ->references('id')
                ->on('dispatch_logs')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bounce_logs');
    }
};
