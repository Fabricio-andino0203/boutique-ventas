<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Campos para replicar el alta de productos estilo Scrampi: marca como
     * entidad, galería de imágenes, opciones de inventario y propagación de
     * la imagen principal a las variantes.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('brand_id')->nullable()->after('subcategory_id')->constrained('brands')->nullOnDelete();
            $table->json('gallery')->nullable()->after('image_path');
            $table->boolean('manage_stock')->default(true)->after('allow_discount');
            $table->boolean('allow_negative_stock')->default(false)->after('manage_stock');
            $table->boolean('variable_price')->default(false)->after('allow_negative_stock');
            $table->boolean('propagate_main_image')->default(false)->after('variable_price');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('brand_id');
            $table->dropColumn(['gallery', 'manage_stock', 'allow_negative_stock', 'variable_price', 'propagate_main_image']);
        });
    }
};
