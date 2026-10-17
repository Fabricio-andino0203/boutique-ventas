<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    /**
     * Crea el usuario propietario del SaaS (super-admin del backoffice).
     * Credenciales tomadas del entorno (ADMIN_EMAIL / ADMIN_PASSWORD) para no versionarlas.
     * Si no se define contraseña en el entorno, se genera una aleatoria y se muestra una vez.
     */
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@boutiqueventas.com');
        $name = env('ADMIN_NAME', 'Administrador');
        $password = env('ADMIN_PASSWORD');

        if (empty($password)) {
            $password = Str::password(16);
            $this->command?->warn("Contraseña generada para {$email}: {$password}");
            $this->command?->warn('Guárdala ahora y cámbiala tras el primer ingreso.');
        }

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'is_super_admin' => true,
                'tenant_id' => null,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );
    }
}
