@php $total = $this->totalLabels; @endphp
<x-filament-panels::page>
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

        {{-- Panel izquierdo: productos --}}
        <x-filament::section>
            <x-slot name="heading">Productos</x-slot>
            <x-slot name="description">Buscá por nombre o escaneá un código: con lector, el producto se agrega solo.</x-slot>

            <div class="space-y-4">
                <div class="flex gap-2">
                    <div class="flex-1">
                        <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
                            <x-filament::input
                                type="text"
                                wire:model.live.debounce.400ms="search"
                                placeholder="Buscar o escanear código..."
                            />
                        </x-filament::input.wrapper>
                    </div>
                    <x-filament::button color="gray" icon="heroicon-o-plus" wire:click="addPage">
                        Esta página
                    </x-filament::button>
                </div>

                <div class="divide-y divide-gray-100 dark:divide-white/5">
                    @forelse ($this->products as $product)
                        @php $count = $product->variants->count(); @endphp
                        <div wire:key="prod-{{ $product->id }}" class="py-2">
                            <div class="flex items-center justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ $product->name }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $count }} {{ $count === 1 ? 'variante' : 'variantes' }}
                                    </p>
                                </div>
                                <div class="flex items-center gap-1">
                                    @if ($count > 1)
                                        <x-filament::button size="sm" color="gray" wire:click="toggleExpand({{ $product->id }})">
                                            Ver
                                        </x-filament::button>
                                    @endif
                                    <x-filament::button size="sm" icon="heroicon-m-plus" wire:click="addProduct({{ $product->id }})">
                                        Agregar
                                    </x-filament::button>
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

        {{-- Panel derecho: etiquetas + opciones --}}
        <div class="space-y-6">
            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center justify-between">
                        <span>Etiquetas</span>
                        <span @class([
                            'rounded-full px-2 py-0.5 text-xs font-semibold',
                            'bg-primary-100 text-primary-700 dark:bg-primary-500/20 dark:text-primary-400' => $total <= \App\Filament\Boutique\Pages\Barcodes::MAX_LABELS,
                        ])>{{ $total }} / {{ \App\Filament\Boutique\Pages\Barcodes::MAX_LABELS }}</span>
                    </div>
                </x-slot>

                <div class="space-y-3">
                    <div class="flex gap-2">
                        <x-filament::button size="sm" color="gray" icon="heroicon-o-squares-2x2" wire:click="segunStock"
                            :disabled="empty($selected)">
                            Según stock
                        </x-filament::button>
                        <x-filament::button size="sm" color="danger" icon="heroicon-o-trash" wire:click="limpiar"
                            :disabled="empty($selected)">
                            Limpiar
                        </x-filament::button>
                    </div>

                    @if (empty($selected))
                        <p class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                            Agregá productos desde el panel de la izquierda.
                        </p>
                    @else
                        <div class="max-h-80 space-y-2 overflow-y-auto">
                            @foreach ($selected as $item)
                                <div wire:key="sel-{{ $item['variant_id'] }}"
                                    class="flex items-center justify-between gap-2 rounded-lg border border-gray-100 p-2 dark:border-white/5">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium text-gray-900 dark:text-white">{{ $item['name'] }}</p>
                                        <p class="font-mono text-xs text-gray-500 dark:text-gray-400">{{ $item['code'] }} · L {{ number_format($item['price'], 2) }}</p>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <x-filament::icon-button icon="heroicon-m-minus" size="sm" color="gray" wire:click="decrement({{ $item['variant_id'] }})" label="Menos" />
                                        <span class="w-8 text-center text-sm font-semibold">{{ $item['quantity'] }}</span>
                                        <x-filament::icon-button icon="heroicon-m-plus" size="sm" color="gray" wire:click="increment({{ $item['variant_id'] }})" label="Más" />
                                        <x-filament::icon-button icon="heroicon-m-x-mark" size="sm" color="danger" wire:click="remove({{ $item['variant_id'] }})" label="Quitar" />
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Opciones de impresión</x-slot>

                <div class="space-y-4 text-sm">
                    <div>
                        <p class="mb-1 font-medium text-gray-700 dark:text-gray-300">Mostrar en la etiqueta</p>
                        <div class="flex gap-4">
                            <label class="flex items-center gap-2">
                                <input type="checkbox" wire:model.live="showName" class="rounded border-gray-300 text-primary-600" /> Nombre
                            </label>
                            <label class="flex items-center gap-2">
                                <input type="checkbox" wire:model.live="showPrice" class="rounded border-gray-300 text-primary-600" /> Precio
                            </label>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <label class="block">
                            <span class="mb-1 block font-medium text-gray-700 dark:text-gray-300">Debajo de las barras</span>
                            <x-filament::input.wrapper>
                                <x-filament::input.select wire:model.live="belowBars">
                                    <option value="code">El código</option>
                                    <option value="name">El nombre</option>
                                </x-filament::input.select>
                            </x-filament::input.wrapper>
                        </label>
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
                        <label class="block">
                            <span class="mb-1 block font-medium text-gray-700 dark:text-gray-300">Etiquetas por fila</span>
                            <x-filament::input.wrapper>
                                <x-filament::input type="number" min="1" max="6" wire:model.live="perRow" />
                            </x-filament::input.wrapper>
                        </label>
                    </div>

                    @if (($printer ?? 'normal') === 'thermal')
                        <p class="rounded-lg bg-warning-50 p-3 text-xs text-warning-700 dark:bg-warning-500/10 dark:text-warning-400">
                            Para la térmica: papel {{ $labelWidth }}×{{ $labelHeight }} mm, márgenes "ninguno", escala 100% y sin encabezados ni pies de página.
                        </p>
                    @endif

                    <x-filament::button class="w-full" size="lg" icon="heroicon-o-printer" wire:click="imprimir"
                        :disabled="empty($selected)">
                        Imprimir etiquetas
                    </x-filament::button>
                </div>
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>
