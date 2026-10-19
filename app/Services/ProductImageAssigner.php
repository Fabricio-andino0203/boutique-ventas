<?php

namespace App\Services;

use App\Models\Product;

/**
 * Asignación masiva de imágenes: imagen principal del producto (con opción de
 * propagarla a las variantes sin imagen) e imagen por valor de opción, que se
 * aplica a todas las variantes que comparten ese valor.
 */
class ProductImageAssigner
{
    /**
     * Guarda la imagen principal del producto. Si $propagate, la copia a las
     * variantes que no tengan imagen propia.
     */
    public function assignMain(Product $product, ?string $path, bool $propagate = false): void
    {
        $product->image_path = $path;
        $product->save();

        if ($propagate && filled($path)) {
            $product->variants()
                ->where(function ($query): void {
                    $query->whereNull('image_path')->orWhere('image_path', '');
                })
                ->update(['image_path' => $path]);
        }
    }

    /**
     * Asigna una imagen a todas las variantes cuyo valor de la opción indicada
     * coincide (p. ej. opción "Color" valor "Rojo").
     *
     * @return int número de variantes actualizadas
     */
    public function assignByOptionValue(Product $product, string $option, string $value, ?string $path): int
    {
        $updated = 0;

        foreach ($product->variants()->get() as $variant) {
            $options = $variant->options ?? [];
            if (isset($options[$option]) && (string) $options[$option] === $value) {
                $variant->image_path = $path;
                $variant->save();
                $updated++;
            }
        }

        return $updated;
    }

    /**
     * Valores de opción distintos del producto, agrupados por nombre de opción.
     * Ej.: ['Color' => ['Rojo', 'Azul'], 'Talla' => ['S', 'M']].
     *
     * @return array<string, array<int, string>>
     */
    public function optionValues(Product $product): array
    {
        $groups = [];

        foreach ($product->variants()->get() as $variant) {
            foreach (($variant->options ?? []) as $option => $value) {
                $value = (string) $value;
                if ($value === '') {
                    continue;
                }
                $groups[$option] ??= [];
                if (! in_array($value, $groups[$option], true)) {
                    $groups[$option][] = $value;
                }
            }
        }

        return $groups;
    }

    /**
     * Opciones para un select "Opción: Valor" → clave "Opción|||Valor".
     *
     * @return array<string, string>
     */
    public function optionValueSelectOptions(Product $product): array
    {
        $options = [];
        foreach ($this->optionValues($product) as $option => $values) {
            foreach ($values as $value) {
                $options[$option.'|||'.$value] = $option.': '.$value;
            }
        }

        return $options;
    }

    /** Cuántas variantes del producto tienen imagen propia. */
    public function variantsWithImage(Product $product): int
    {
        return $product->variants()
            ->whereNotNull('image_path')
            ->where('image_path', '!=', '')
            ->count();
    }

    /** Si el producto (o alguna de sus variantes) ya tiene imagen. */
    public function hasAnyImage(Product $product): bool
    {
        if (filled($product->image_path)) {
            return true;
        }

        return $this->variantsWithImage($product) > 0;
    }
}
