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
        // Número correlativo visible de cada documento: 'C-000001' para compras
        // y 'D-000001' para devoluciones. Se asigna en la acción transaccional.
        Schema::table('purchases', function (Blueprint $table) {
            $table->string('number')->nullable()->after('user_id');
            $table->index(['tenant_id', 'number']);
        });

        Schema::table('sale_returns', function (Blueprint $table) {
            $table->string('number')->nullable()->after('user_id');
            $table->index(['tenant_id', 'number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'number']);
            $table->dropColumn('number');
        });

        Schema::table('sale_returns', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'number']);
            $table->dropColumn('number');
        });
    }
};
