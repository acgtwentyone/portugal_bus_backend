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
        Schema::create('arrival_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('stop_id');
            $table->string('route_id');
            $table->unsignedTinyInteger('direction_id');
            $table->timestamp('estimated_arrival_time'); // as observed by the client when the alert was activated
            $table->unsignedTinyInteger('threshold_minutes')->default(5); // notify when the bus is this many minutes out
            $table->string('device_token'); // FCM token, no PII
            $table->string('locale', 5)->default('en'); // app language at activation time, used to localize the push
            $table->timestamps();

            $table->index(['stop_id', 'route_id', 'direction_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('arrival_alerts');
    }
};
