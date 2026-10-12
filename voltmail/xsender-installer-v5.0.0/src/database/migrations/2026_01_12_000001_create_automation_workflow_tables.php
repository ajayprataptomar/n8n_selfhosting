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
        // Main workflows table
        if (!Schema::hasTable('automation_workflows')) {
            Schema::create('automation_workflows', function (Blueprint $table) {
                $table->id();
                $table->string('uid', 32)->unique();
                $table->unsignedInteger('user_id')->nullable()->index();
                $table->string('name');
                $table->text('description')->nullable();
                $table->enum('status', ['draft', 'active', 'paused'])->default('draft');
                $table->string('trigger_type')->nullable();
                $table->json('trigger_config')->nullable();
                $table->unsignedInteger('total_enrolled')->default(0);
                $table->unsignedInteger('total_completed')->default(0);
                $table->unsignedInteger('total_failed')->default(0);
                $table->timestamp('last_triggered_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['user_id', 'status']);
                $table->index(['trigger_type', 'status']);
            });
        }

        // Workflow nodes (steps in the workflow)
        if (!Schema::hasTable('workflow_nodes')) {
            Schema::create('workflow_nodes', function (Blueprint $table) {
                $table->id();
                $table->string('uid', 32)->unique();
                $table->unsignedBigInteger('workflow_id')->index();
                $table->enum('type', ['trigger', 'action', 'condition', 'wait'])->default('action');
                $table->string('action_type')->nullable();
                $table->json('config')->nullable();
                $table->string('label')->nullable();
                $table->integer('position_x')->default(0);
                $table->integer('position_y')->default(0);
                $table->unsignedBigInteger('next_node_id')->nullable();
                $table->unsignedBigInteger('condition_true_node_id')->nullable();
                $table->unsignedBigInteger('condition_false_node_id')->nullable();
                $table->unsignedInteger('order')->default(0);
                $table->timestamps();

                $table->index(['workflow_id', 'type']);
                $table->index('next_node_id');
            });
        }

        // Workflow executions (contacts going through workflows)
        if (!Schema::hasTable('workflow_executions')) {
            Schema::create('workflow_executions', function (Blueprint $table) {
                $table->id();
                $table->string('uid', 32)->unique();
                $table->unsignedBigInteger('workflow_id')->index();
                $table->unsignedBigInteger('contact_id')->index();
                $table->unsignedBigInteger('current_node_id')->nullable();
                $table->enum('status', ['running', 'waiting', 'completed', 'failed', 'cancelled'])->default('running');
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('next_action_at')->nullable();
                $table->json('context')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamps();

                $table->index(['workflow_id', 'status']);
                $table->index(['contact_id', 'status']);
                $table->index(['status', 'next_action_at']);
            });
        }

        // Workflow execution logs (history of each step)
        if (!Schema::hasTable('workflow_execution_logs')) {
            Schema::create('workflow_execution_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('execution_id')->index();
                $table->unsignedBigInteger('node_id')->index();
                $table->string('action')->nullable();
                $table->enum('result', ['success', 'failed', 'skipped'])->default('success');
                $table->json('data')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamp('executed_at');

                $table->index(['execution_id', 'executed_at']);
            });
        }

        // Workflow triggers log (when workflows are triggered)
        if (!Schema::hasTable('workflow_trigger_logs')) {
            Schema::create('workflow_trigger_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('workflow_id')->index();
                $table->string('trigger_type');
                $table->unsignedInteger('contacts_enrolled')->default(0);
                $table->json('trigger_data')->nullable();
                $table->timestamp('triggered_at');
                $table->timestamps();

                $table->index(['workflow_id', 'triggered_at']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_trigger_logs');
        Schema::dropIfExists('workflow_execution_logs');
        Schema::dropIfExists('workflow_executions');
        Schema::dropIfExists('workflow_nodes');
        Schema::dropIfExists('automation_workflows');
    }
};
