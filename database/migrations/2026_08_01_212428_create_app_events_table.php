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
        Schema::create('app_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_type');           // review_prompt_triggered | review_prompt_shown | review_completed | review_dismissed
            $table->string('device_id')->nullable(); // anonymous install/device identifier, not PII
            $table->json('metadata')->nullable();     // free-form extra context for future events
            $table->timestamp('created_at')->useCurrent();

            $table->index('event_type');
            $table->index('device_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_events');
    }
};
