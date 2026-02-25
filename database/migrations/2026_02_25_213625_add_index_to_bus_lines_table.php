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
        Schema::table('bus_lines', function (Blueprint $table) {
            $table->index(['network', 'code'], 'idx_bus_lines_ordering');
            $table->index('name', 'idx_bus_lines_name_search');
            $table->index('code', 'idx_bus_lines_code_search');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bus_lines', function (Blueprint $table) {
            $table->dropIndex('idx_bus_lines_ordering');
            $table->dropIndex('idx_bus_lines_name_search');
            $table->dropIndex('idx_bus_lines_code_search');
        });
    }
};
