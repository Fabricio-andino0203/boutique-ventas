<?php

namespace App\Filament\Boutique\Resources\Orders\Pages;

use App\Filament\Boutique\Resources\Orders\OrderResource;
use App\Filament\Boutique\Resources\Orders\Schemas\OrderInfolist;
use App\Models\Order;
use App\Services\OrderService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    public function infolist(Schema $schema): Schema
    {
        return OrderInfolist::configure($schema);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('registrarPago')
                ->label('Registrar pago')
                ->icon(Heroicon::OutlinedBanknotes)
                ->color('success')
                ->visible(fn (Order $record): bool => $record->due > 0 && $record->status !== 'cancelled')
                ->schema([
                    TextInput::make('amount')
                        ->label('Monto')
                        ->numeric()
                        ->prefix('L')
                        ->required()
                        ->default(fn (Order $record): float => $record->due),
                    Select::make('method')
                        ->label('Método')
                        ->options(['cash' => 'Efectivo', 'card' => 'Tarjeta', 'transfer' => 'Transferencia'])
                        ->default('cash')
                        ->required(),
                ])
                ->action(function (array $data, Order $record): void {
                    try {
                        app(OrderService::class)->registerPayment($record, (float) $data['amount'], $data['method']);
                        Notification::make()->title('Pago registrado.')->success()->send();
                    } catch (\Throwable $e) {
                        Notification::make()->title('No se pudo registrar el pago')->body($e->getMessage())->danger()->send();
                    }
                }),

            Action::make('cambiarEstado')
                ->label('Cambiar estado')
                ->icon(Heroicon::OutlinedArrowsRightLeft)
                ->visible(fn (Order $record): bool => $record->status !== 'cancelled')
                ->schema([
                    Select::make('status')
                        ->label('Estado')
                        ->options(['pending' => 'Pendiente', 'completed' => 'Completado'])
                        ->default(fn (Order $record): string => $record->status)
                        ->required(),
                ])
                ->action(function (array $data, Order $record): void {
                    $old = $record->status;
                    $record->update(['status' => $data['status']]);
                    $record->movements()->create([
                        'user_id' => Auth::id(),
                        'type' => 'status',
                        'description' => "Estado cambiado de $old a {$data['status']}.",
                    ]);
                    Notification::make()->title('Estado actualizado.')->success()->send();
                }),

            Action::make('whatsapp')
                ->label('Contactar al cliente')
                ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                ->color('success')
                ->visible(fn (Order $record): bool => filled($record->phone))
                ->url(fn (Order $record): ?string => filled($record->phone)
                    ? 'https://wa.me/'.preg_replace('/\D/', '', (string) $record->phone)
                    : null, true),

            Action::make('cancelar')
                ->label('Cancelar pedido')
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription('Esta acción marca el pedido como cancelado.')
                ->visible(fn (Order $record): bool => $record->status !== 'cancelled' && ! $record->sale_id)
                ->action(function (Order $record): void {
                    $record->update(['status' => 'cancelled']);
                    $record->movements()->create([
                        'user_id' => Auth::id(),
                        'type' => 'cancelled',
                        'description' => 'Pedido cancelado.',
                    ]);
                    Notification::make()->title('Pedido cancelado.')->warning()->send();
                }),
        ];
    }
}
