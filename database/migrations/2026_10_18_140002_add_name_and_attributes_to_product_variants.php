<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nombre compuesto y atributos (opción => valor) de cada variante, para el
     * generador de combinaciones (ej. {"Talla":"M","Color":"Rojo"}).
     */
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->string('name')->nullable()->after('product_id');
            $table->json('options')->nullable()->after('model');
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn(['name', 'options']);
        });
    }
};
