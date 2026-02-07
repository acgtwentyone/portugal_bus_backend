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
        Schema::create('bus_lines', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();           // Ex: "500", "1M"
            $table->string('name');                     // Ex: "Matosinhos (Mercado) - Aliados"
            $table->string('network');                  // Ex: "diurna", "madrugada"
            $table->string('slug')->unique();           // Para o URL da API ou da Web
            $table->timestamp('last_sync')->nullable(); // Data da última atualização
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bus_lines');
    }
};
