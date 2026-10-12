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
    public function up(): void
    {
        if (Schema::hasTable('content_generation_histories')) {
            return;
        }

        Schema::create('content_generation_histories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('uid', 100)->unique()->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('content_resource_id')->nullable()->index();
            $table->unsignedBigInteger('gateway_id')->nullable()->index();
            $table->string('template_id', 100)->index();
            $table->string('template_version', 20)->default('1.0.0');
            $table->string('subject', 255);
            $table->longText('body');
            $table->string('content_fingerprint', 64)->index();
            $table->json('variables_used')->nullable();
            $table->unsignedInteger('reuse_count')->default(1);
            $table->timestamp('last_used_at')->nullable();
            $table->string('dispatch_reference', 100)->nullable()->index();
            $table->timestamps();

            $table->index(['user_id', 'content_fingerprint']);
            $table->index(['user_id', 'content_resource_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('content_generation_histories');
    }
};
