<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('sending_domains')) {
            return;
        }

        Schema::create('sending_domains', function (Blueprint $table) {
            $table->id();
            $table->string('uid', 100)->unique();
            $table->unsignedInteger('user_id')->nullable();
            $table->string('domain', 255);
            $table->string('dkim_selector', 63)->default('xsender');
            $table->text('dkim_private_key')->nullable();
            $table->text('dkim_public_key')->nullable();
            $table->text('dkim_dns_record')->nullable();
            $table->string('spf_record', 500)->nullable();
            $table->string('dmarc_record', 500)->nullable();
            $table->enum('dkim_verified', ['yes', 'no'])->default('no');
            $table->enum('spf_verified', ['yes', 'no'])->default('no');
            $table->enum('dmarc_verified', ['yes', 'no'])->default('no');
            $table->enum('status', ['active', 'inactive', 'pending'])->default('pending')->index();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('dns_checked_at')->nullable();
            $table->timestamps();
        });

        try {
            Schema::table('sending_domains', function (Blueprint $table) {
                $table->foreign('user_id')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        } catch (\Exception $e) {
            // Foreign key may fail on some setups
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sending_domains');
    }
};
