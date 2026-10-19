@php
    use Illuminate\Support\Facades\Storage;
    $mediaDisk = config('filesystems.media');
    $progress = $this->progress;
    $pct = $progress['total'] > 0 ? round($progress['withImage'] / $progress['total'] * 100) : 0;
@endphp

<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                <div class="flex-1">
                    <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
                        <x-filament::input
                            type="text"
                            wire:model.live.debounce.400ms="search"
                            placeholder="Buscar producto por nombre..."
                        />
                    </x-filament::input.wrapper>
                </div>
                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <input type="checkbox" wire:model.live="onlyWithoutImage"
                        class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500" />
                    Solo sin imagen
                </label>
            </div>

            <div class="mt-4">
                <div class="mb-1 flex justify-between text-sm font-medium text-gray-600 dark:text-gray-400">
                    <span>{{ $progress['withImage'] }} de {{ $progress['total'] }} con imagen</span>
                    <span>{{ $pct }}%</span>
                </div>
                <div class="h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                    <div class="h-full rounded-full bg-primary-600 transition-all" style="width: {{ $pct }}%"></div>
                </div>
            </div>
        </x-filament::section>

        @if ($this->products->isEmpty())
            <x-filament::section>
                <p class="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                    No se encontraron productos{{ $onlyWithoutImage ? ' sin imagen' : '' }}.
                </p>
            </x-filament::section>
        @else
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($this->products as $product)
                    @php
                        $hasMain = filled($product->image_path);
                        $variantsWithImage = $product->variants->filter(fn ($v) => filled($v->image_path))->count();
                        $hasAny = $hasMain || $variantsWithImage > 0;
                        $url = $hasMain ? Storage::disk($mediaDisk)->url($product->image_path) : null;
                    @endphp
                    <div wire:key="prod-{{ $product->id }}"
                        class="flex flex-col overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
                        <div class="relative aspect-square bg-gray-50 dark:bg-white/5">
                            @if ($url)
                                <img src="{{ $url }}" alt="{{ $product->name }}" class="h-full w-full object-contain" />
                            @else
                                <div class="flex h-full w-full items-center justify-center text-gray-300 dark:text-gray-600">
                                    <x-filament::icon icon="heroicon-o-photo" class="h-12 w-12" />
                                </div>
                            @endif
                            <span @class([
                                'absolute right-2 top-2 rounded-full px-2 py-0.5 text-xs font-medium',
                                'bg-success-100 text-success-700 dark:bg-success-500/20 dark:text-success-400' => $hasAny,
                                'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-400' => ! $hasAny,
                            ])>
                                {{ $hasAny ? 'Con imagen' : 'Sin imagen' }}
                            </span>
                        </div>
                        <div class="flex flex-1 flex-col gap-2 p-3">
                            <p class="line-clamp-2 text-sm font-semibold text-gray-900 dark:text-white">{{ $product->name }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                @if ($product->variants->count() > 1)
                                    {{ $product->variants->count() }} variantes · {{ $variantsWithImage }} con foto
                                @else
                                    Producto simple
                                @endif
                            </p>
                            <div class="mt-auto pt-1">
                                <x-filament::button
                                    size="sm"
                                    color="gray"
                                    class="w-full"
                                    icon="heroicon-o-arrow-up-tray"
                                    wire:click="mountAction('asignarImagen', { product: {{ $product->id }} })"
                                >
                                    {{ $hasAny ? 'Cambiar imagen' : 'Subir imagen' }}
                                </x-filament::button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
