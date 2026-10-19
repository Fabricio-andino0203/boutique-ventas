<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Combos: productos que agrupan a otros y se venden como uno solo.
     *
     * Cada combo se respalda con un Product(type='combo') + 1 ProductVariant
     * (la "variante combo") para reusar POS, venta, inventario y recibos sin
     * cambiar sus esquemas. Las columnas de precio/stock de aquí son la fuente
     * de verdad del combo; la variante combo se mantiene sincronizada.
     */
    public function up(): void
    {
        Schema::create('combos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subcategory_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            $table->string('image_path')->nullable();
            // Cómo se cobra: cheapest | up_to_price | all_same
            $table->string('pricing_mode')->default('cheapest');
            // Precio del combo ("Cuánto cuesta").
            $table->decimal('price', 12, 2)->default(0);
            // Precio base para el modo up_to_price (por encima se cobra la diferencia).
            $table->decimal('base_price', 12, 2)->nullable();
            // Stock: derived (descuenta componentes) | own (stock propio).
            $table->string('stock_mode')->default('derived');
            $table->integer('stock_quantity')->default(0);
            $table->integer('min_stock')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('combos');
    }
};
