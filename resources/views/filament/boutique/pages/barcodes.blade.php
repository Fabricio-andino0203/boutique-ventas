@php
    $total = $this->totalLabels;
    $max = \App\Filament\Boutique\Pages\Barcodes::MAX_LABELS;
@endphp
<x-filament-panels::page>
    {{-- Estilos locales (la vista previa usa mm, como la impresión real) --}}
    <style>
        .bc-label {
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            overflow: hidden; text-align: center; background: #fff; color: #111827;
            border: 1px dashed #d1d5db; border-radius: 4px; padding: 1.2mm;
        }
        .bc-label .name { font-size: 2.2mm; font-weight: 600; line-height: 1.05; max-height: 5mm; overflow: hidden; width: 100%; }
        .bc-label svg { width: 100%; height: auto; }
        .bc-label .below { font-size: 2mm; font-family: ui-monospace, monospace; letter-spacing: .3mm; margin-top: .3mm; }
        .bc-label .price { font-size: 2.6mm; font-weight: 700; margin-top: .3mm; }
    </style>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

        {{-- ========================= PRODUCTOS ========================= --}}
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center justify-between gap-2">
                    <span>Productos</span>
                    <x-filament::button size="sm" color="gray" icon="heroicon-o-document-plus" wire:click="addPage">
                        Agregar esta página
                    </x-filament::button>
                </div>
            </x-slot>
            <x-slot name="description">Buscá o escaneá un código: con el lector, el producto se agrega solo.</x-slot>

            <div class="space-y-4">
                <x-filament::input.wrapper prefix-icon="heroicon-m-qr-code">
                    <x-filament::input
                        type="text"
                        wire:model.live.debounce.400ms="search"
                        placeholder="Buscá por nombre o escaneá un código"
                    />
                </x-filament::input.wrapper>

                @if ($this->productsWithoutBarcode > 0)
                    <div class="flex items-center justify-between gap-3 rounded-lg bg-primary-50 p-3 text-sm dark:bg-primary-500/10">
                        <span class="text-gray-600 dark:text-gray-300">
                            <span class="font-semibold text-gray-900 dark:text-white">{{ $this->productsWithoutBarcode }}</span>
                            {{ $this->productsWithoutBarcode === 1 ? 'producto no tiene' : 'productos no tienen' }} código de barras
                            y no {{ $this->productsWithoutBarcode === 1 ? 'aparece' : 'aparecen' }} en esta lista.
                        </span>
                        <x-filament::button size="sm" icon="heroicon-o-bars-4" wire:click="generarCodigos">
                            Generar códigos
                        </x-filament::button>
                    </div>
                @endif

                <div class="divide-y divide-gray-100 dark:divide-white/5">
                    @forelse ($this->products as $product)
                        @php
                            $count = $product->variants->count();
                            $selectedQty = collect($selected)
                                ->whereIn('variant_id', $product->variants->pluck('id')->all())
                                ->sum('quantity');
                        @endphp
                        <div wire:key="prod-{{ $product->id }}" class="py-2">
                            <div @class([
                                'flex items-center justify-between gap-2 rounded-lg px-2 py-1.5',
                                'bg-primary-50 dark:bg-primary-500/10' => $selectedQty > 0,
                            ])>
                                <div class="flex min-w-0 items-center gap-3">
                                    @svg('heroicon-o-cube', 'shrink-0 text-gray-400', ['style' => 'width:1.5rem;height:1.5rem'])
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-gray-900 dark:text-white">
                                            {{ $product->name }}
                                            @if ($selectedQty > 0)
                                                <span class="ml-1 text-xs font-bold text-primary-600 dark:text-primary-400">×{{ $selectedQty }}</span>
                                            @endif
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ $count }} {{ $count === 1 ? 'variante' : 'variantes' }}
                                        </p>
                                    </div>
                                </div>
                                <div class="flex shrink-0 items-center gap-1">
                                    @if ($count > 1)
                                        <button type="button" wire:click="toggleExpand({{ $product->id }})"
                                            class="flex items-center gap-1 rounded-md px-2 py-1 text-sm text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-white/5">
                                            Ver @svg('heroicon-m-chevron-right', '', ['style' => 'width:1rem;height:1rem'])
                                        </button>
                                    @endif
                                    <x-filament::icon-button icon="heroicon-m-plus" color="primary"
                                        wire:click="addProductAll({{ $product->id }})" label="Agregar" />
                                </div>
                            </div>

                            @if ($expandedProduct === $product->id && $count > 1)
                                <div class="mt-2 space-y-1 rounded-lg bg-gray-50 p-2 dark:bg-white/5">
                                    @foreach ($product->variants as $variant)
                                        <div wire:key="var-{{ $variant->id }}" class="flex items-center justify-between gap-2 text-sm">
                                            <span class="truncate text-gray-700 dark:text-gray-300">
                                                {{ trim(($variant->size ?? '').' '.($variant->color ?? '')) ?: ($variant->name ?: 'Variante') }}
                                                <span class="text-xs text-gray-400">· stock {{ $variant->stock_quantity }}</span>
                                            </span>
                                            <x-filament::button size="xs" icon="heroicon-m-plus" wire:click="addVariant({{ $variant->id }})">
                                                Agregar
                                            </x-filament::button>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @empty
                        <p class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">No se encontraron productos.</p>
                    @endforelse
                </div>
            </div>
        </x-filament::section>

        {{-- ========================= ETIQUETAS ========================= --}}
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <span>Etiquetas</span>
                        <span @class([
                            'rounded-full px-2 py-0.5 text-xs font-semibold',
                            'bg-primary-100 text-primary-700 dark:bg-primary-500/20 dark:text-primary-400' => $total <= $max,
                            'bg-danger-100 text-danger-700 dark:bg-danger-500/20 dark:text-danger-400' => $total > $max,
                        ])>{{ $total }}</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <button type="button" wire:click="segunStock" @disabled(empty($selected))
                            class="flex items-center gap-1 rounded-md px-2 py-1 text-sm text-gray-500 hover:bg-gray-100 disabled:opacity-40 dark:text-gray-400 dark:hover:bg-white/5">
                            @svg('heroicon-o-squares-2x2', '', ['style' => 'width:1rem;height:1rem']) Según stock
                        </button>
                        <x-filament::icon-button icon="heroicon-o-trash" color="danger" wire:click="limpiar"
                            :disabled="empty($selected)" label="Limpiar" />
                    </div>
                </div>
            </x-slot>
            <x-slot name="description">Hasta {{ $max }} etiquetas por impresión.</x-slot>

            <div class="space-y-4">
                @if (empty($selected))
                    <p class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                        Agregá productos desde el panel de la izquierda.
                    </p>
                @else
                    <div class="max-h-80 space-y-2 overflow-y-auto pr-1">
                        @foreach ($selected as $item)
                            <div wire:key="sel-{{ $item['variant_id'] }}"
                                class="flex items-center justify-between gap-2 rounded-lg border border-gray-100 p-2 dark:border-white/5">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ $item['name'] }}</p>
                                    <p class="mt-0.5 flex items-center gap-1.5">
                                        <span class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:bg-white/10 dark:text-gray-400">EAN-13</span>
                                        <span class="font-mono text-xs text-gray-500 dark:text-gray-400">{{ $item['code'] }}</span>
                                    </p>
                                </div>
                                <div class="flex shrink-0 items-center gap-1">
                                    <x-filament::icon-button icon="heroicon-m-minus" size="sm" color="gray" wire:click="decrement({{ $item['variant_id'] }})" label="Menos" />
                                    <span class="w-8 text-center text-sm font-semibold">{{ $item['quantity'] }}</span>
                                    <x-filament::icon-button icon="heroicon-m-plus" size="sm" color="gray" wire:click="increment({{ $item['variant_id'] }})" label="Más" />
                                    <x-filament::icon-button icon="heroicon-m-x-mark" size="sm" color="danger" wire:click="remove({{ $item['variant_id'] }})" label="Quitar" />
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Opciones --}}
                <div class="space-y-4 border-t border-gray-100 pt-4 text-sm dark:border-white/10">
                    <div class="flex items-center justify-between">
                        <span class="font-medium text-gray-700 dark:text-gray-300">Mostrar en la etiqueta</span>
                        <div class="flex gap-4">
                            <label class="flex items-center gap-2">
                                <input type="checkbox" wire:model.live="showName" class="rounded border-gray-300 text-primary-600" /> Nombre
                            </label>
                            <label class="flex items-center gap-2">
                                <input type="checkbox" wire:model.live="showPrice" class="rounded border-gray-300 text-primary-600" /> Precio
                            </label>
                        </div>
                    </div>

                    <div>
                        <span class="mb-1 block font-medium text-gray-700 dark:text-gray-300">Debajo de las barras</span>
                        <div class="inline-flex rounded-lg border border-gray-200 p-0.5 dark:border-white/10">
                            <button type="button" wire:click="$set('belowBars', 'code')" @class([
                                'rounded-md px-3 py-1 text-sm font-medium transition',
                                'bg-primary-600 text-white' => $belowBars === 'code',
                                'text-gray-600 dark:text-gray-300' => $belowBars !== 'code',
                            ])>El código</button>
                            <button type="button" wire:click="$set('belowBars', 'name')" @class([
                                'rounded-md px-3 py-1 text-sm font-medium transition',
                                'bg-primary-600 text-white' => $belowBars === 'name',
                                'text-gray-600 dark:text-gray-300' => $belowBars !== 'name',
                            ])>El nombre</button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <label class="block">
                            <span class="mb-1 block font-medium text-gray-700 dark:text-gray-300">Impresora</span>
                            <x-filament::input.wrapper>
                                <x-filament::input.select wire:model.live="printer">
                                    <option value="normal">Hoja normal</option>
                                    <option value="thermal">Térmica de etiquetas</option>
                                </x-filament::input.select>
                            </x-filament::input.wrapper>
                        </label>
                        <label class="block">
                            <span class="mb-1 block font-medium text-gray-700 dark:text-gray-300">Ancho (mm)</span>
                            <x-filament::input.wrapper>
                                <x-filament::input type="number" min="20" max="100" wire:model.live="labelWidth" />
                            </x-filament::input.wrapper>
                        </label>
                        <label class="block">
                            <span class="mb-1 block font-medium text-gray-700 dark:text-gray-300">Alto (mm)</span>
                            <x-filament::input.wrapper>
                                <x-filament::input type="number" min="10" max="100" wire:model.live="labelHeight" />
                            </x-filament::input.wrapper>
                        </label>
                    </div>

                    @if (($printer ?? 'normal') === 'normal')
                        <label class="block">
                            <span class="mb-1 block font-medium text-gray-700 dark:text-gray-300">Etiquetas por fila</span>
                            <x-filament::input.wrapper>
                                <x-filament::input type="number" min="1" max="6" wire:model.live="perRow" />
                            </x-filament::input.wrapper>
                        </label>
                    @else
                        <p class="rounded-lg bg-warning-50 p-3 text-xs text-warning-700 dark:bg-warning-500/10 dark:text-warning-400">
                            Para la térmica: papel {{ $labelWidth }}×{{ $labelHeight }} mm, márgenes "ninguno", escala 100% y sin encabezados ni pies de página.
                        </p>
                    @endif

                    <x-filament::button class="w-full" size="lg" icon="heroicon-o-printer" wire:click="imprimir"
                        :disabled="empty($selected)">
                        Imprimir etiquetas
                    </x-filament::button>
                </div>
            </div>
        </x-filament::section>
    </div>

    {{-- ========================= VISTA PREVIA ========================= --}}
    <x-filament::section class="mt-6">
        <x-slot name="heading">Vista previa</x-slot>
        <x-slot name="description">Así se verán las etiquetas con las opciones elegidas.</x-slot>

        @if (empty($this->previewLabels))
            <p class="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                Agregá productos para ver la vista previa de sus etiquetas.
            </p>
        @else
            <div class="flex flex-wrap gap-3">
                @foreach ($this->previewLabels as $label)
                    <div class="bc-label" style="width: {{ $labelWidth }}mm; height: {{ $labelHeight }}mm;">
                        @if ($showName)
                            <div class="name">{{ $label['name'] }}</div>
                        @endif
                        {!! $label['svg'] !!}
                        <div class="below">{{ $belowBars === 'name' ? $label['name'] : $label['code'] }}</div>
                        @if ($showPrice)
                            <div class="price">L {{ number_format($label['price'], 2) }}</div>
                        @endif
                    </div>
                @endforeach
            </div>
            @if ($total > count($this->previewLabels))
                <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                    Mostrando {{ count($this->previewLabels) }} de {{ $total }} etiquetas. Al imprimir se generan todas.
                </p>
            @endif
        @endif
    </x-filament::section>
</x-filament-panels::page>
