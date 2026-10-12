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
        if (Schema::hasTable('content_resources')) {
            return;
        }

        Schema::create('content_resources', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('uid', 100)->unique()->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('name', 191);
            $table->string('company_name', 191);
            $table->string('business_type', 100)->nullable();
            $table->text('company_description')->nullable();
            $table->string('website', 191)->nullable();
            $table->string('industry', 100)->nullable();
            $table->text('target_audience')->nullable();
            $table->string('brand_voice', 100)->nullable();
            $table->json('writing_preferences')->nullable();
            $table->json('products_services')->nullable();
            $table->json('faqs')->nullable();
            $table->json('templates')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'is_default']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('content_resources');
    }
};
