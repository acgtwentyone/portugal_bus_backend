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
        // Append-only audit trail of admin panel write actions.
        Schema::create('admin_actions', function (Blueprint $table) {
            $table->id();
            $table->string('admin_email');
            $table->string('action');
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('payload');
            $table->string('ip')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_actions');
    }
};
