<?php

namespace App\Filament\Boutique\Pages;

use App\Services\ProductImporter;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportProducts extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.boutique.pages.import-products';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static string|\UnitEnum|null $navigationGroup = 'Carga masiva';

    protected static ?int $navigationSort = 1;

    /** @var array<string, mixed> */
    public ?array $data = [];

    /** @var array{created:int, errors:array<int, string>}|null */
    public ?array $resultado = null;

    public static function getNavigationLabel(): string
    {
        return 'Importar productos';
    }

    public function getTitle(): string
    {
        return 'Importar productos';
    }

    public function getSubheading(): ?string
    {
        return 'Carga masiva de productos desde un archivo Excel (.xlsx, .xls).';
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('file')
                    ->label('Archivo Excel')
                    ->helperText('Formatos: .xlsx, .xls (máximo 5 MB).')
                    ->acceptedFileTypes([
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/vnd.ms-excel',
                    ])
                    ->maxSize(5120)
                    ->disk('local')
                    ->directory('imports')
                    ->required(),
            ])
            ->statePath('data');
    }

    public function descargarPlantilla(): StreamedResponse
    {
        $spreadsheet = (new ProductImporter)->buildTemplate();

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new XlsxWriter($spreadsheet))->save('php://output');
        }, 'plantilla-productos.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function importar(): void
    {
        $state = $this->form->getState();
        $file = $state['file'] ?? null;

        if (is_array($file)) {
            $file = reset($file);
        }

        if (! $file) {
            Notification::make()->title('Seleccioná un archivo primero.')->danger()->send();

            return;
        }

        $path = Storage::disk('local')->path($file);

        $this->resultado = (new ProductImporter)->import($path);

        Storage::disk('local')->delete($file);
        $this->form->fill();

        $creados = $this->resultado['created'];
        $errores = count($this->resultado['errors']);

        Notification::make()
            ->title("Importación finalizada: $creados productos creados".($errores ? ", $errores con error" : '.'))
            ->{$errores ? 'warning' : 'success'}()
            ->send();
    }
}
