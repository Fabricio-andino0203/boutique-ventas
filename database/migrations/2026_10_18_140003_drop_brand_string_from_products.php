<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La marca pasó a ser una entidad (brand_id). Se elimina la columna de
     * texto legada "brand" que entraba en conflicto con la relación brand().
     */
    public function up(): void
    {
        if (Schema::hasColumn('products', 'brand')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('brand');
            });
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('brand')->nullable()->after('name');
        });
    }
};
