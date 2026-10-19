<?php

namespace App\Filament\Boutique\Pages;

use App\Models\Product;
use App\Services\ProductImageAssigner;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;

class ProductImages extends Page implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    protected string $view = 'filament.boutique.pages.product-images';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static string|\UnitEnum|null $navigationGroup = 'Carga masiva';

    protected static ?int $navigationSort = 2;

    public string $search = '';

    public bool $onlyWithoutImage = false;

    public static function getNavigationLabel(): string
    {
        return 'Imágenes de productos';
    }

    public function getTitle(): string
    {
        return 'Imágenes de productos';
    }

    public function getSubheading(): ?string
    {
        return 'Asigná una foto a cada producto, o una por valor de opción que cubra todas las variantes que lo comparten.';
    }

    /**
     * @return Collection<int, Product>
     */
    #[Computed]
    public function products(): Collection
    {
        $assigner = app(ProductImageAssigner::class);

        return Product::query()
            ->with('variants')
            ->when($this->search !== '', fn ($query) => $query->where('name', 'like', '%'.$this->search.'%'))
            ->orderBy('name')
            ->get()
            ->when($this->onlyWithoutImage, fn (Collection $products) => $products->reject(
                fn (Product $product): bool => $assigner->hasAnyImage($product)
            ))
            ->values();
    }

    /**
     * @return array{total:int, withImage:int}
     */
    #[Computed]
    public function progress(): array
    {
        $assigner = app(ProductImageAssigner::class);
        $all = Product::query()->with('variants')->get();

        return [
            'total' => $all->count(),
            'withImage' => $all->filter(fn (Product $product): bool => $assigner->hasAnyImage($product))->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function findProduct(array $arguments): ?Product
    {
        $id = $arguments['product'] ?? null;

        return $id ? Product::with('variants')->find($id) : null;
    }

    public function asignarImagenAction(): Action
    {
        return Action::make('asignarImagen')
            ->label('Asignar imagen')
            ->modalHeading(fn (array $arguments): string => 'Imagen de '.($this->findProduct($arguments)?->name ?? 'producto'))
            ->modalSubmitActionLabel('Guardar')
            ->fillForm(function (array $arguments): array {
                $product = $this->findProduct($arguments);

                return [
                    'image_path' => $product?->image_path,
                    'propagate' => false,
                ];
            })
            ->schema(function (array $arguments): array {
                $product = $this->findProduct($arguments);
                $isVariable = $product !== null && $product->variants->count() > 1;
                $valueOptions = $product !== null
                    ? app(ProductImageAssigner::class)->optionValueSelectOptions($product)
                    : [];

                return [
                    FileUpload::make('image_path')
                        ->label('Imagen principal')
                        ->image()
                        ->disk(config('filesystems.media'))
                        ->directory('products')
                        ->visibility('public')
                        ->maxSize(5120)
                        ->imageResizeMode('contain')
                        ->imageResizeTargetWidth(1200)
                        ->imageResizeTargetHeight(1200)
                        ->imageEditor()
                        ->helperText('Portada del producto. Máximo 5 MB; se optimiza automáticamente.'),
                    Toggle::make('propagate')
                        ->label('Aplicar esta imagen a las variantes sin imagen')
                        ->visible($isVariable),
                    Repeater::make('by_value')
                        ->label('Imagen por valor de opción')
                        ->helperText('Cada imagen se aplica a todas las variantes que comparten ese valor (ej. "Color: Rojo").')
                        ->visible($isVariable && $valueOptions !== [])
                        ->schema([
                            Select::make('key')
                                ->label('Valor de opción')
                                ->options($valueOptions)
                                ->required(),
                            FileUpload::make('image')
                                ->label('Imagen')
                                ->image()
                                ->disk(config('filesystems.media'))
                                ->directory('products')
                                ->visibility('public')
                                ->maxSize(5120)
                                ->imageResizeMode('contain')
                                ->imageResizeTargetWidth(1200)
                                ->imageResizeTargetHeight(1200),
                        ])
                        ->addActionLabel('Agregar valor de opción')
                        ->defaultItems(0)
                        ->columns(2),
                ];
            })
            ->action(function (array $arguments, array $data): void {
                $product = $this->findProduct($arguments);
                if ($product === null) {
                    return;
                }

                $assigner = app(ProductImageAssigner::class);
                $assigner->assignMain($product, $data['image_path'] ?? null, (bool) ($data['propagate'] ?? false));

                foreach ($data['by_value'] ?? [] as $row) {
                    if (empty($row['key'])) {
                        continue;
                    }
                    [$option, $value] = array_pad(explode('|||', (string) $row['key'], 2), 2, '');
                    $assigner->assignByOptionValue($product, $option, $value, $row['image'] ?? null);
                }

                unset($this->products, $this->progress);

                Notification::make()->title('Imágenes actualizadas.')->success()->send();
            });
    }
}
