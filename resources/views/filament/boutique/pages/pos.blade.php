<x-filament-panels::page>
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- Columna izquierda: búsqueda + carrito --}}
        <div class="space-y-4 lg:col-span-2">
            <div class="fi-section rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">
                    Buscar producto (nombre, SKU, código, talla o color)
                </label>
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Escribe para buscar…"
                    autofocus
                    class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-white"
                />

                @if (strlen(trim($search)) > 0)
                    <div class="mt-3 divide-y divide-gray-100 overflow-hidden rounded-lg ring-1 ring-gray-950/5 dark:divide-white/10 dark:ring-white/10">
                        @forelse ($this->results as $variant)
                            <button
                                type="button"
                                wire:click="addToCart({{ $variant->id }})"
                                class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left hover:bg-primary-50 dark:hover:bg-white/5"
                            >
                                <span>
                                    <span class="font-medium text-gray-900 dark:text-white">{{ $variant->product->name ?? 'Producto' }}</span>
                                    <span class="block text-xs text-gray-500 dark:text-gray-400">
                                        {{ collect([$variant->size, $variant->color, $variant->model])->filter()->implode(' / ') ?: 'Única' }}
                                        · Stock: {{ $variant->stock_quantity }}
                                    </span>
                                </span>
                                <span class="shrink-0 font-semibold text-primary-600 dark:text-primary-400">L {{ number_format($variant->price, 2) }}</span>
                            </button>
                        @empty
                            <p class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">Sin coincidencias.</p>
                        @endforelse
                    </div>
                @endif
            </div>

            {{-- Carrito --}}
            <div class="fi-section overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 dark:bg-white/5 dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-3">Producto</th>
                            <th class="px-4 py-3 text-center">Cantidad</th>
                            <th class="px-4 py-3 text-right">Precio</th>
                            <th class="px-4 py-3 text-right">Subtotal</th>
                            <th class="px-2 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                        @forelse ($cart as $item)
                            <tr>
                                <td class="px-4 py-3 text-gray-900 dark:text-white">{{ $item['label'] }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-center gap-2">
                                        <button type="button" wire:click="decrementQty({{ $item['id'] }})" class="flex h-7 w-7 items-center justify-center rounded-md bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-white/10 dark:text-white">−</button>
                                        <span class="w-8 text-center font-medium text-gray-900 dark:text-white">{{ $item['qty'] }}</span>
                                        <button type="button" wire:click="incrementQty({{ $item['id'] }})" class="flex h-7 w-7 items-center justify-center rounded-md bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-white/10 dark:text-white">+</button>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">L {{ number_format($item['price'], 2) }}</td>
                                <td class="px-4 py-3 text-right font-medium text-gray-900 dark:text-white">L {{ number_format($item['price'] * $item['qty'], 2) }}</td>
                                <td class="px-2 py-3 text-right">
                                    <button type="button" wire:click="removeFromCart({{ $item['id'] }})" class="text-danger-600 hover:text-danger-500" title="Quitar">✕</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">
                                    El carrito está vacío. Busca productos para agregarlos.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Columna derecha: cobro --}}
        <div class="space-y-4">
            <div class="fi-section space-y-4 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Cliente (opcional)</label>
                    <select wire:model="customerId" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-white">
                        <option value="">Consumidor final</option>
                        @foreach ($this->customers as $customer)
                            <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="space-y-2 border-t border-gray-100 pt-3 dark:border-white/10">
                    <div class="flex justify-between text-sm text-gray-600 dark:text-gray-300">
                        <span>Subtotal</span>
                        <span>L {{ number_format($this->subtotal(), 2) }}</span>
                    </div>
                    <div class="flex items-center justify-between text-sm text-gray-600 dark:text-gray-300">
                        <span>Descuento</span>
                        <input type="number" min="0" step="0.01" wire:model.live.debounce.400ms="discount" class="w-28 rounded-lg border-gray-300 text-right shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-white" />
                    </div>
                    <div class="flex justify-between border-t border-gray-100 pt-2 text-lg font-bold text-gray-900 dark:border-white/10 dark:text-white">
                        <span>Total</span>
                        <span>L {{ number_format($this->total(), 2) }}</span>
                    </div>
                </div>

                <div class="space-y-3 border-t border-gray-100 pt-3 dark:border-white/10">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Método de pago</label>
                        <select wire:model="paymentMethod" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-white">
                            <option value="cash">Efectivo</option>
                            <option value="card">Tarjeta</option>
                            <option value="transfer">Transferencia</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Monto recibido</label>
                        <input type="number" min="0" step="0.01" wire:model.live.debounce.400ms="amountPaid" class="block w-full rounded-lg border-gray-300 text-right shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-white" />
                    </div>
                    <div class="flex justify-between text-sm font-medium text-gray-700 dark:text-gray-200">
                        <span>Cambio</span>
                        <span>L {{ number_format($this->change(), 2) }}</span>
                    </div>
                </div>

                <x-filament::button
                    wire:click="register"
                    wire:loading.attr="disabled"
                    size="lg"
                    class="w-full justify-center"
                    :disabled="count($cart) === 0"
                >
                    Registrar venta
                </x-filament::button>
            </div>
        </div>
    </div>
</x-filament-panels::page>
