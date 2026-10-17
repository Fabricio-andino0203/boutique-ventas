<?php

namespace App\Filament\Resources\Payments\Tables;

use App\Models\Payment;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tenant.name')
                    ->label('Comercio')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('plan.name')
                    ->label('Plan')
                    ->searchable(),
                TextColumn::make('amount')
                    ->label('Monto')
                    ->money('HNL')
                    ->sortable(),
                TextColumn::make('method')
                    ->label('Método')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'cash' => 'Efectivo',
                        'bank_transfer' => 'Transferencia',
                        default => $state,
                    }),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'confirmed' => 'success',
                        'rejected' => 'danger',
                        default => 'warning',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'confirmed' => 'Confirmado',
                        'rejected' => 'Rechazado',
                        'pending' => 'Pendiente',
                        default => $state,
                    }),
                TextColumn::make('reference')
                    ->label('Referencia')
                    ->toggleable(),
                TextColumn::make('paid_at')
                    ->label('Fecha de pago')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('confirmedBy.name')
                    ->label('Confirmado por')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'pending' => 'Pendiente',
                        'confirmed' => 'Confirmado',
                        'rejected' => 'Rechazado',
                    ]),
            ])
            ->recordActions([
                Action::make('confirmar')
                    ->label('Confirmar')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (Payment $record): bool => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->modalHeading('Confirmar pago recibido')
                    ->modalDescription('Confirma solo después de verificar personalmente que el dinero fue recibido.')
                    ->action(function (Payment $record): void {
                        $record->update([
                            'status' => 'confirmed',
                            'confirmed_at' => now(),
                            'confirmed_by' => Auth::id(),
                        ]);
                    }),
                Action::make('rechazar')
                    ->label('Rechazar')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->visible(fn (Payment $record): bool => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->modalHeading('Rechazar pago')
                    ->action(function (Payment $record): void {
                        $record->update([
                            'status' => 'rejected',
                            'confirmed_at' => now(),
                            'confirmed_by' => Auth::id(),
                        ]);
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
