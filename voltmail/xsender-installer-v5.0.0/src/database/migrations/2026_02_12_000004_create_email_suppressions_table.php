<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('email_suppressions')) {
            return;
        }

        Schema::create('email_suppressions', function (Blueprint $table) {
            $table->id();
            $table->string('uid', 100)->unique();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('email_address', 255);
            $table->enum('reason', ['hard_bounce', 'complaint', 'manual', 'unsubscribe'])->index();
            $table->enum('source', ['webhook', 'import', 'manual', 'system'])->default('system');
            $table->timestamp('created_at')->nullable();

            $table->unique(['email_address', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_suppressions');
    }
};
