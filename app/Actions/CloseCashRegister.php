<?php

namespace App\Actions;

use App\Models\CashRegister;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cierra una caja abierta de forma atómica. El efectivo esperado es el fondo de
 * apertura más la suma de las ventas en efectivo ligadas a esa caja; la
 * diferencia es el conteo real (closing_balance) menos lo esperado.
 */
class CloseCashRegister
{
    public function execute(CashRegister $register, float $closingBalance = 0, ?string $notes = null): CashRegister
    {
        return DB::transaction(function () use ($register, $closingBalance, $notes): CashRegister {
            /** @var CashRegister $register */
            $register = CashRegister::query()->lockForUpdate()->findOrFail($register->id);

            if ($register->status !== 'open') {
                throw ValidationException::withMessages([
                    'cash_register' => 'Esta caja ya está cerrada.',
                ]);
            }

            $cashSales = (float) $register->sales()
                ->where('status', 'completed')
                ->where('payment_method', 'cash')
                ->sum('total');

            $expected = (float) $register->opening_balance + $cashSales;
            $closing = max(0.0, $closingBalance);

            $register->update([
                'status' => 'closed',
                'closed_by' => Auth::id(),
                'closing_balance' => $closing,
                'expected_balance' => $expected,
                'difference' => $closing - $expected,
                'closed_at' => now(),
                'notes' => $notes ?? $register->notes,
            ]);

            return $register;
        });
    }
}
