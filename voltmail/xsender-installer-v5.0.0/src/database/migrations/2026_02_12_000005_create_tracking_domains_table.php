<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tracking_domains')) {
            return;
        }

        Schema::create('tracking_domains', function (Blueprint $table) {
            $table->id();
            $table->string('uid', 100)->unique();
            $table->unsignedInteger('user_id')->nullable();
            $table->string('domain', 255);
            $table->enum('status', ['active', 'inactive', 'pending'])->default('pending')->index();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->unique(['domain', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracking_domains');
    }
};
