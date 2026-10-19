<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Campos de catálogo para productos (tipo, subcategoría, proveedor, unidad,
     * código, código de barras, precio mayorista, stock mínimo, descuentos e
     * impuestos a nivel de producto).
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('type')->default('simple')->after('name'); // simple | variable
            $table->foreignId('subcategory_id')->nullable()->after('category_id')->constrained('categories')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->after('subcategory_id')->constrained('suppliers')->nullOnDelete();
            $table->string('code')->nullable()->after('brand');
            $table->string('barcode')->nullable()->after('code');
            $table->string('unit')->nullable()->after('barcode');
            $table->decimal('wholesale_price', 12, 2)->nullable()->after('default_cost');
            $table->integer('min_stock')->default(0)->after('wholesale_price');
            $table->boolean('allow_discount')->default(true)->after('min_stock');
            $table->string('tax_type')->default('none')->after('allow_discount'); // none | included | additional
            $table->decimal('tax_rate', 5, 2)->default(0)->after('tax_type');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('subcategory_id');
            $table->dropConstrainedForeignId('supplier_id');
            $table->dropColumn(['type', 'code', 'barcode', 'unit', 'wholesale_price', 'min_stock', 'allow_discount', 'tax_type', 'tax_rate']);
        });
    }
};
