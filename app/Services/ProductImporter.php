<?php

namespace App\Services;

use App\Filament\Boutique\Resources\Products\Schemas\ProductForm;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Carga masiva de productos desde Excel (.xlsx/.xls), estilo Scrampi.
 * Genera la plantilla de ejemplo y procesa el archivo subido.
 */
class ProductImporter
{
    /** @var array<int, string> Encabezados de la plantilla, en orden. */
    public const COLUMNS = [
        'Nombre', 'Precio', 'Costo', 'Stock', 'Stock minimo', 'Precio mayorista',
        'Codigo de barras', 'Categoria', 'Subcategoria', 'Marca', 'Proveedor', 'Unidad',
        'Descripcion', 'Impuesto', 'Tasa impuesto', 'Gestionar stock', 'Precio variable',
        'Stock negativo', 'Permitir descuentos',
        'Opcion 1', 'Valores Opcion 1', 'Opcion 2', 'Valores Opcion 2', 'Opcion 3', 'Valores Opcion 3',
    ];

    /**
     * Construye la plantilla de ejemplo con encabezados, filas de ejemplo
     * (simple y con variantes) y una hoja de Instrucciones.
     */
    public function buildTemplate(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Productos');

        // Encabezados.
        foreach (self::COLUMNS as $i => $name) {
            $cell = $this->columnLetter($i).'1';
            $sheet->setCellValue($cell, $name);
        }
        $sheet->getStyle('A1:'.$this->columnLetter(count(self::COLUMNS) - 1).'1')
            ->getFont()->setBold(true);
        $sheet->getStyle('A1:'.$this->columnLetter(count(self::COLUMNS) - 1).'1')
            ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F43F78');
        $sheet->getStyle('A1:'.$this->columnLetter(count(self::COLUMNS) - 1).'1')
            ->getFont()->getColor()->setRGB('FFFFFF');

        // Fila de ejemplo: producto simple.
        $simple = [
            'Camisa clásica', 350, 180, 20, 2, 300, '', 'Ropa', 'Camisas', 'Mi Marca', 'Proveedor 1',
            'Unidad', 'Camisa de algodón', 'included', 15, 'SI', 'NO', 'NO', 'SI',
            '', '', '', '', '', '',
        ];
        // Fila de ejemplo: producto con variantes.
        $variable = [
            'Vestido floral', 750, 400, 0, 1, 650, '', 'Vestidos', '', 'Mi Marca', 'Proveedor 1',
            'Unidad', 'Vestido con estampado', 'included', 15, 'SI', 'NO', 'NO', 'SI',
            'Talla', 'S, M, L, XL', 'Color', 'Rojo, Azul, Negro', '', '',
        ];
        $this->writeRow($sheet, 2, $simple);
        $this->writeRow($sheet, 3, $variable);

        foreach (range(0, count(self::COLUMNS) - 1) as $i) {
            $sheet->getColumnDimension($this->columnLetter($i))->setAutoSize(true);
        }

        // Hoja de instrucciones.
        $help = $spreadsheet->createSheet();
        $help->setTitle('Instrucciones');
        $lines = [
            'INSTRUCCIONES DE IMPORTACIÓN',
            '',
            'Las columnas Nombre y Precio son obligatorias.',
            'Las categorías deben existir previamente en el sistema (Categoría y Subcategoría por nombre).',
            'Marca y Proveedor se crean automáticamente si no existen.',
            'Campos booleanos (Gestionar stock, Precio variable, Stock negativo, Permitir descuentos): SI, SÍ, TRUE o 1 para activar.',
            'Impuesto: none (sin impuesto), included (incluido en el precio) o additional (se suma). Tasa impuesto en % (ej. 15).',
            '',
            'PRODUCTO CON VARIANTES:',
            'Escribe el nombre de la opción en "Opcion 1" (ej. Color) y sus valores separados por coma en "Valores Opcion 1" (ej. Rojo, Azul, Negro).',
            'Puedes usar hasta 3 opciones. Se crea una variante por cada combinación.',
            'El precio/costo/stock de la fila se usan como valores por defecto de todas las variantes.',
            '',
            'Borra las filas de ejemplo antes de importar.',
        ];
        foreach ($lines as $i => $line) {
            $help->setCellValue('A'.($i + 1), $line);
        }
        $help->getColumnDimension('A')->setWidth(110);
        $help->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $help->getStyle('A1:A'.count($lines))->getAlignment()->setWrapText(true);

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    /**
     * Procesa el archivo subido y crea los productos.
     *
     * @return array{created:int, errors:array<int, string>}
     */
    public function import(string $filePath): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, false, false);

        if (count($rows) < 2) {
            return ['created' => 0, 'errors' => ['El archivo no tiene filas de datos.']];
        }

        $headerMap = $this->mapHeaders($rows[0]);
        $created = 0;
        $errors = [];

        foreach (array_slice($rows, 1, null, true) as $index => $row) {
            $line = $index + 1; // número de fila en el Excel

            $get = fn (string $col) => $this->value($row, $headerMap, $col);

            $name = trim((string) $get('Nombre'));
            if ($name === '') {
                continue; // fila vacía
            }

            $price = $get('Precio');
            if ($price === null || $price === '') {
                $errors[] = "Fila $line: falta el Precio (obligatorio).";

                continue;
            }

            try {
                DB::transaction(function () use ($get, $name, $price): void {
                    $this->createProductFromRow($get, $name, (float) $price);
                });
                $created++;
            } catch (\Throwable $e) {
                $errors[] = "Fila $line ($name): ".$e->getMessage();
            }
        }

        return ['created' => $created, 'errors' => $errors];
    }

    /**
     * Crea un producto (simple o variable) a partir de una fila.
     *
     * @param  callable(string):mixed  $get
     */
    private function createProductFromRow(callable $get, string $name, float $price): void
    {
        $categoryName = trim((string) $get('Categoria'));
        $subcategoryName = trim((string) $get('Subcategoria'));
        $category = $categoryName !== ''
            ? Category::query()->whereNull('parent_id')->whereRaw('LOWER(name) = ?', [mb_strtolower($categoryName)])->first()
            : null;

        if ($categoryName !== '' && ! $category) {
            throw new \RuntimeException("La categoría \"$categoryName\" no existe.");
        }

        $subcategory = null;
        if ($subcategoryName !== '' && $category) {
            $subcategory = Category::query()->where('parent_id', $category->id)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($subcategoryName)])->first();
            if (! $subcategory) {
                throw new \RuntimeException("La subcategoría \"$subcategoryName\" no existe en \"$categoryName\".");
            }
        }

        $brandName = trim((string) $get('Marca'));
        $brand = $brandName !== '' ? Brand::firstOrCreate(['name' => $brandName]) : null;

        $supplierName = trim((string) $get('Proveedor'));
        $supplier = $supplierName !== '' ? Supplier::firstOrCreate(['name' => $supplierName]) : null;

        $taxType = strtolower(trim((string) $get('Impuesto'))) ?: 'none';
        if (! in_array($taxType, ['none', 'included', 'additional'], true)) {
            $taxType = 'none';
        }

        $cost = (float) ($get('Costo') ?: 0);
        $stock = (int) ($get('Stock') ?: 0);
        $minStock = (int) ($get('Stock minimo') ?: 0);
        $wholesale = $get('Precio mayorista');
        $barcode = trim((string) $get('Codigo de barras')) ?: null;

        // ¿Tiene opciones? -> producto variable.
        $options = [];
        foreach ([1, 2, 3] as $n) {
            $optName = trim((string) $get("Opcion $n"));
            $optValues = trim((string) $get("Valores Opcion $n"));
            if ($optName !== '' && $optValues !== '') {
                $values = array_values(array_filter(array_map('trim', explode(',', $optValues)), fn ($v) => $v !== ''));
                if ($values !== []) {
                    $options[] = ['name' => $optName, 'values' => $values];
                }
            }
        }

        $type = $options !== [] ? 'variable' : 'simple';

        $product = Product::create([
            'type' => $type,
            'name' => $name,
            'description' => trim((string) $get('Descripcion')) ?: null,
            'category_id' => $category?->id,
            'subcategory_id' => $subcategory?->id,
            'brand_id' => $brand?->id,
            'supplier_id' => $supplier?->id,
            'unit' => trim((string) $get('Unidad')) ?: null,
            'code' => null,
            'barcode' => $barcode,
            'default_price' => $price,
            'default_cost' => $cost,
            'wholesale_price' => ($wholesale === null || $wholesale === '') ? null : (float) $wholesale,
            'min_stock' => $minStock,
            'manage_stock' => $this->bool($get('Gestionar stock'), true),
            'variable_price' => $this->bool($get('Precio variable'), false),
            'allow_negative_stock' => $this->bool($get('Stock negativo'), false),
            'allow_discount' => $this->bool($get('Permitir descuentos'), true),
            'tax_type' => $taxType,
            'tax_rate' => (float) ($get('Tasa impuesto') ?: 0),
            'is_active' => true,
        ]);

        if ($type === 'variable') {
            $variants = ProductForm::buildVariantsFromOptions(
                $options,
                ['price' => $price, 'cost' => $cost, 'stock' => $stock],
                ' - ',
                $name,
            );
            foreach ($variants as $variant) {
                $product->variants()->create($variant);
            }
        } else {
            $product->variants()->create([
                'name' => $name,
                'barcode' => $barcode,
                'price' => $price,
                'cost' => $cost,
                'wholesale_price' => ($wholesale === null || $wholesale === '') ? null : (float) $wholesale,
                'stock_quantity' => $stock,
                'min_stock' => $minStock,
                'is_active' => true,
            ]);
        }
    }

    /**
     * Mapa de encabezado normalizado => índice de columna.
     *
     * @param  array<int, mixed>  $headerRow
     * @return array<string, int>
     */
    private function mapHeaders(array $headerRow): array
    {
        $map = [];
        foreach ($headerRow as $i => $header) {
            $map[$this->normalize((string) $header)] = $i;
        }

        return $map;
    }

    /**
     * Valor de una columna por nombre lógico (tolerante a acentos/mayúsculas).
     *
     * @param  array<int, mixed>  $row
     * @param  array<string, int>  $headerMap
     */
    private function value(array $row, array $headerMap, string $column): mixed
    {
        $key = $this->normalize($column);
        $i = $headerMap[$key] ?? null;

        return $i === null ? null : ($row[$i] ?? null);
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = strtr($value, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n']);

        return preg_replace('/\s+/', ' ', $value) ?? $value;
    }

    private function bool(mixed $value, bool $default): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return in_array(mb_strtoupper(trim((string) $value)), ['SI', 'SÍ', 'TRUE', '1', 'X', 'VERDADERO'], true);
    }

    /**
     * @param  array<int, mixed>  $values
     */
    private function writeRow(Worksheet $sheet, int $rowNumber, array $values): void
    {
        foreach ($values as $i => $value) {
            $sheet->setCellValue($this->columnLetter($i).$rowNumber, $value);
        }
    }

    private function columnLetter(int $index): string
    {
        return Coordinate::stringFromColumnIndex($index + 1);
    }
}
