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
        // One row per scheduled task execution, written by RecordScheduledTaskRun. Pruned after 7 days.
        Schema::create('scheduled_task_runs', function (Blueprint $table) {
            $table->id();
            $table->string('command');
            $table->string('status'); // running | success | failed | skipped
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->smallInteger('exit_code')->nullable();
            $table->text('error')->nullable();

            $table->index(['command', 'started_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scheduled_task_runs');
    }
};
