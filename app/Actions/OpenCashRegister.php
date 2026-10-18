<?php

namespace App\Actions;

use App\Models\CashRegister;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Abre una caja para un usuario de forma atómica. Valida que el usuario no tenga
 * ya una caja abierta antes de crear la nueva (el aislamiento por comercio lo
 * aporta el TenantScope del modelo).
 */
class OpenCashRegister
{
    public function execute(User $user, float $openingBalance = 0, ?string $notes = null): CashRegister
    {
        return DB::transaction(function () use ($user, $openingBalance, $notes): CashRegister {
            $alreadyOpen = CashRegister::query()
                ->where('opened_by', $user->id)
                ->where('status', 'open')
                ->lockForUpdate()
                ->exists();

            if ($alreadyOpen) {
                throw ValidationException::withMessages([
                    'cash_register' => 'Ya tienes una caja abierta. Ciérrala antes de abrir otra.',
                ]);
            }

            return CashRegister::create([
                'opened_by' => $user->id,
                'status' => 'open',
                'opening_balance' => max(0.0, $openingBalance),
                'opened_at' => now(),
                'notes' => $notes,
            ]);
        });
    }
}
