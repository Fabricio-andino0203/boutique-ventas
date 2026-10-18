<?php

namespace App\Filament\Boutique\Pages;

use App\Filament\Boutique\Reportes\ProfitByDayChart;
use App\Filament\Boutique\Reportes\ProfitStats;
use App\Filament\Boutique\Reportes\SellerProfit;
use App\Filament\Boutique\Reportes\TopProfitableProducts;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use UnitEnum;

/**
 * Reporte de utilidad del comercio para un rango de fechas. Un formulario de
 * filtros (desde/hasta, por defecto el mes actual) alimenta los widgets de
 * Reportes, que resuelven las métricas, los desgloses y la gráfica. Todas las
 * consultas quedan aisladas por comercio gracias al TenantScope de los modelos.
 */
class Reportes extends Page
{
    use HasFiltersForm;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    protected static string|UnitEnum|null $navigationGroup = 'Reportes';

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return 'Reportes';
    }

    public function getTitle(): string
    {
        return 'Reportes de utilidad';
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('desde')
                    ->label('Desde')
                    ->default(Carbon::now()->startOfMonth())
                    ->maxDate(fn (callable $get) => $get('hasta') ?: now())
                    ->native(false)
                    ->closeOnDateSelection(),
                DatePicker::make('hasta')
                    ->label('Hasta')
                    ->default(Carbon::now()->endOfMonth())
                    ->minDate(fn (callable $get) => $get('desde'))
                    ->native(false)
                    ->closeOnDateSelection(),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getFiltersFormContentComponent(),
                $this->getWidgetsContentComponent(),
            ]);
    }

    public function getFiltersFormContentComponent(): Component
    {
        return EmbeddedSchema::make('filtersForm');
    }

    public function getWidgetsContentComponent(): Component
    {
        return Grid::make($this->getColumns())
            ->schema(fn (): array => $this->getWidgetsSchemaComponents($this->getWidgets()));
    }

    /**
     * @return array<class-string<Widget>>
     */
    public function getWidgets(): array
    {
        return [
            ProfitStats::class,
            ProfitByDayChart::class,
            TopProfitableProducts::class,
            SellerProfit::class,
        ];
    }

    /**
     * @return int|array<string, ?int>
     */
    public function getColumns(): int|array
    {
        return 1;
    }
}
