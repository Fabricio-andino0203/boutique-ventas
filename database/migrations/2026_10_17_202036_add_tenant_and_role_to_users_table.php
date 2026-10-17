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
        Schema::table('users', function (Blueprint $table) {
            // Boutique a la que pertenece el usuario. NULL = usuario del backoffice (propietario del SaaS).
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            // Propietario del SaaS: acceso al backoffice de administración.
            $table->boolean('is_super_admin')->default(false)->after('password');
            // Rol dentro de la boutique: owner | manager | cashier.
            $table->string('role')->nullable()->after('is_super_admin');
            $table->string('phone')->nullable()->after('role');
            $table->boolean('is_active')->default(true)->after('phone');

            $table->index(['tenant_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropColumn(['tenant_id', 'is_super_admin', 'role', 'phone', 'is_active']);
        });
    }
};
