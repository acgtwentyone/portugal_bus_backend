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
        Schema::table('arrival_alerts', function (Blueprint $table) {
            // Identifies the exact bus trip from the STCP realtime feed, so we track that
            // specific arrival instead of "next of this route".
            $table->string('trip_id')->after('direction_id');
            $table->index(['stop_id', 'trip_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('arrival_alerts', function (Blueprint $table) {
            $table->dropIndex(['stop_id', 'trip_id']);
            $table->dropColumn('trip_id');
        });
    }
};
