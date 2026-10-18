<?php

namespace App\Filament\Boutique\Resources\CashRegisters\Pages;

use App\Actions\CloseCashRegister;
use App\Actions\OpenCashRegister;
use App\Filament\Boutique\Resources\CashRegisters\CashRegisterResource;
use App\Models\CashRegister;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ListCashRegisters extends ListRecords
{
    protected static string $resource = CashRegisterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('abrirCaja')
                ->label('Abrir caja')
                ->icon(Heroicon::OutlinedLockOpen)
                ->color('success')
                ->visible(fn (): bool => $this->openRegister() === null)
                ->schema([
                    TextInput::make('opening_balance')
                        ->label('Fondo de apertura')
                        ->numeric()
                        ->prefix('L')
                        ->minValue(0)
                        ->default(0)
                        ->required(),
                    Textarea::make('notes')
                        ->label('Notas')
                        ->rows(2),
                ])
                ->action(function (array $data): void {
                    try {
                        app(OpenCashRegister::class)->execute(
                            Auth::user(),
                            (float) $data['opening_balance'],
                            $data['notes'] ?? null,
                        );
                    } catch (ValidationException $e) {
                        Notification::make()
                            ->title('No se pudo abrir la caja')
                            ->body(collect($e->errors())->flatten()->first())
                            ->danger()
                            ->send();

                        return;
                    }

                    Notification::make()->title('Caja abierta')->success()->send();
                }),
            Action::make('cerrarCaja')
                ->label('Cerrar caja')
                ->icon(Heroicon::OutlinedLockClosed)
                ->color('danger')
                ->visible(fn (): bool => $this->openRegister() !== null)
                ->schema([
                    TextInput::make('closing_balance')
                        ->label('Efectivo contado')
                        ->numeric()
                        ->prefix('L')
                        ->minValue(0)
                        ->default(0)
                        ->required(),
                    Textarea::make('notes')
                        ->label('Notas')
                        ->rows(2),
                ])
                ->action(function (array $data): void {
                    $register = $this->openRegister();

                    if ($register === null) {
                        Notification::make()->title('No tienes una caja abierta')->warning()->send();

                        return;
                    }

                    try {
                        app(CloseCashRegister::class)->execute(
                            $register,
                            (float) $data['closing_balance'],
                            $data['notes'] ?? null,
                        );
                    } catch (ValidationException $e) {
                        Notification::make()
                            ->title('No se pudo cerrar la caja')
                            ->body(collect($e->errors())->flatten()->first())
                            ->danger()
                            ->send();

                        return;
                    }

                    Notification::make()->title('Caja cerrada')->success()->send();
                }),
        ];
    }

    /**
     * Caja abierta del usuario actual, si existe.
     */
    private function openRegister(): ?CashRegister
    {
        return CashRegister::query()
            ->where('opened_by', Auth::id())
            ->where('status', 'open')
            ->latest('opened_at')
            ->first();
    }
}
