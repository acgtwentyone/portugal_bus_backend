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
        Schema::create('unir_bus_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unir_bus_line_id')->constrained('unir_bus_lines')->onDelete('cascade');
            $table->json('directions_0')->nullable();
            $table->json('directions_1')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unir_bus_stops');
    }
};
