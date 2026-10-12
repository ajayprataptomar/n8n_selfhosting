<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ensure the `templates` table exists via a proper Laravel migration.
 *
 * Before v5.0.1, the templates table was created ONLY by templates.sql
 * (loaded through TemplatesSeeder) via a bare CREATE TABLE with no
 * inline PRIMARY KEY — the PK was added later in an ALTER TABLE. On
 * MySQL servers with `sql_require_primary_key = ON` (default on many
 * managed MySQL 8.4+ installs and some cPanel/Plesk configurations),
 * that CREATE fails, and any subsequent code path that queries
 * `templates` (e.g. SendMail::MailNotification looking up
 * GLOBAL_TEMPLATE) 500s with:
 *
 *   Base table or view not found: 1146 Table 'x_xs.templates' doesn't exist
 *
 * This migration creates the table with the PK declared inline, so the
 * install works even on strict MySQL. The seeder still fills the row
 * data — it will simply skip the CREATE because the table now exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('templates')) {
            return;
        }

        Schema::create('templates', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('uid', 100)->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->enum('channel', ['email', 'sms', 'whatsapp'])->nullable();
            $table->string('name');
            $table->string('slug');
            $table->longText('template_data');
            $table->json('meta_data')->nullable();
            $table->boolean('plugin')->default(false);
            $table->boolean('default')->default(false);
            $table->boolean('global')->default(false);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->enum('approval_status', ['pending', 'approved', 'rejected'])->nullable()->index();
            $table->unsignedBigInteger('cloud_id')->nullable()->comment('WhatsApp Cloud API');
            $table->enum('provider', ['system', 'bee_free', 'ck_editor'])->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('slug');
            $table->index('channel');
        });
    }

    public function down(): void
    {
        // No-op: this table carries seeded system templates (GLOBAL_TEMPLATE,
        // PASSWORD_RESET, VERIFY_EMAIL, INSUFFICIENT_CREDIT, etc.) that other
        // migrations depend on. Dropping it would cascade into notification
        // failures across the app.
    }
};
