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
        // Historial de todo movimiento de existencias por variante.
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // Tipo: purchase | sale | return | adjustment | initial
            $table->string('type');
            // Cantidad con signo: positiva = entrada, negativa = salida.
            $table->integer('quantity');
            $table->integer('balance_after')->nullable();
            // Origen polimórfico del movimiento (venta, compra, devolución, ajuste).
            $table->nullableMorphs('source');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'product_variant_id']);
            $table->index(['tenant_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
