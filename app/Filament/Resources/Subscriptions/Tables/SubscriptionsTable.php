<?php

namespace App\Filament\Resources\Subscriptions\Tables;

use App\Models\Subscription;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class SubscriptionsTable
{
    /**
     * @var array<string, string>
     */
    private const STATUS_LABELS = [
        'pending' => 'Pendiente',
        'active' => 'Activa',
        'suspended' => 'Suspendida',
        'expired' => 'Vencida',
        'cancelled' => 'Cancelada',
    ];

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
                    ->badge()
                    ->color('primary'),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'suspended' => 'warning',
                        'expired', 'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => self::STATUS_LABELS[$state] ?? $state),
                TextColumn::make('price')
                    ->label('Precio')
                    ->money('HNL')
                    ->sortable(),
                TextColumn::make('starts_at')
                    ->label('Inicio')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('ends_at')
                    ->label('Vencimiento')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(self::STATUS_LABELS),
            ])
            ->recordActions([
                Action::make('activar')
                    ->label('Activar')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (Subscription $record): bool => in_array($record->status, ['pending', 'suspended', 'expired', 'cancelled'], true))
                    ->requiresConfirmation()
                    ->modalHeading('Activar suscripción')
                    ->modalDescription('El vencimiento se calcula según la duración del plan contratado.')
                    ->action(function (Subscription $record): void {
                        $start = $record->starts_at ?? now();
                        $durationDays = $record->plan?->duration_days ?? 30;

                        $record->update([
                            'status' => 'active',
                            'starts_at' => $start,
                            'ends_at' => $start->copy()->addDays($durationDays),
                            'cancelled_at' => null,
                            'activated_by' => Auth::id(),
                            'price' => $record->price > 0 ? $record->price : ($record->plan?->price ?? 0),
                        ]);

                        $record->tenant?->update(['status' => 'active']);
                    }),
                Action::make('suspender')
                    ->label('Suspender')
                    ->icon(Heroicon::OutlinedPauseCircle)
                    ->color('warning')
                    ->visible(fn (Subscription $record): bool => $record->status === 'active')
                    ->requiresConfirmation()
                    ->modalHeading('Suspender suscripción')
                    ->action(function (Subscription $record): void {
                        $record->update(['status' => 'suspended']);
                        $record->tenant?->update(['status' => 'suspended']);
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
