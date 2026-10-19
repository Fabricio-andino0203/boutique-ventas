@php
    $total = $this->totalLabels;
    $max = \App\Filament\Boutique\Pages\Barcodes::MAX_LABELS;
@endphp
<x-filament-panels::page>
    {{-- La UI de esta página se estiliza con CSS propio (no con utilidades de
         Tailwind) para que se vea igual aunque el panel no cargue app.css. --}}
    <style>
        .bc {
            --card-bg:#fff; --border:#e5e7eb; --text:#0f172a; --muted:#64748b;
            --row-border:#eef2f7; --soft:#f8fafc; --accent:#F43F78;
            --accent-weak:#fde7ef; --accent-text:#be185d;
            --chip-bg:#f1f5f9; --chip-text:#64748b;
            --input-bg:#fff; --input-border:#d1d5db;
        }
        :is(.dark) .bc {
            --card-bg:#1b2431; --border:rgba(255,255,255,.08); --text:#f1f5f9; --muted:#94a3b8;
            --row-border:rgba(255,255,255,.06); --soft:rgba(255,255,255,.035);
            --accent:#F43F78; --accent-weak:rgba(244,63,120,.15); --accent-text:#f9a8c4;
            --chip-bg:rgba(255,255,255,.08); --chip-text:#cbd5e1;
            --input-bg:rgba(255,255,255,.04); --input-border:rgba(255,255,255,.14);
        }

        .bc { color: var(--text); }
        .bc * { box-sizing: border-box; }
        .bc-cols { display: grid; grid-template-columns: 1fr; gap: 1.25rem; align-items: start; }
        @media (min-width: 1024px) { .bc-cols { grid-template-columns: 1fr 1fr; } }

        .bc-card {
            background: var(--card-bg); border: 1px solid var(--border);
            border-radius: 1rem; padding: 1.25rem;
            box-shadow: 0 1px 2px rgba(15,23,42,.04);
        }
        .bc-card + .bc-card { margin-top: 1.25rem; }
        .bc-head { display:flex; align-items:flex-start; justify-content:space-between; gap:.75rem; }
        .bc-title { font-size: 1.125rem; font-weight: 700; margin: 0; display:flex; align-items:center; gap:.5rem; }
        .bc-subtitle { font-size: .8125rem; color: var(--muted); margin: .25rem 0 0; }
        .bc-count {
            background: var(--accent-weak); color: var(--accent-text);
            border-radius: 999px; padding: .1rem .55rem; font-size: .75rem; font-weight: 700;
        }

        .bc-body { margin-top: 1rem; }

        /* Inputs */
        .bc-input, .bc-select {
            width: 100%; background: var(--input-bg); color: var(--text);
            border: 1px solid var(--input-border); border-radius: .6rem;
            padding: .55rem .75rem; font-size: .875rem; outline: none;
        }
        .bc-input:focus, .bc-select:focus { border-color: var(--accent); box-shadow: 0 0 0 2px var(--accent-weak); }
        .bc-input-icon { position: relative; }
        .bc-input-icon svg { position:absolute; left:.7rem; top:50%; transform:translateY(-50%); color:var(--muted); }
        .bc-input-icon .bc-input { padding-left: 2.25rem; }

        /* Botones */
        .bc-btn {
            display:inline-flex; align-items:center; gap:.4rem; cursor:pointer;
            border:0; border-radius:.6rem; padding:.5rem .85rem; font-size:.8125rem; font-weight:600;
            background: var(--accent); color:#fff;
        }
        .bc-btn:hover { filter: brightness(.95); }
        .bc-btn.ghost { background: var(--soft); color: var(--text); border:1px solid var(--border); }
        .bc-btn.link { background: transparent; color: var(--muted); padding:.35rem .5rem; }
        .bc-btn.link:hover { background: var(--soft); }
        .bc-btn:disabled { opacity:.45; cursor:not-allowed; }
        .bc-print { width:100%; justify-content:center; padding:.8rem; font-size:.95rem; }
        .bc-icon-btn {
            display:inline-flex; align-items:center; justify-content:center; cursor:pointer;
            width:1.9rem; height:1.9rem; border-radius:.5rem; border:1px solid var(--border);
            background: var(--soft); color: var(--text);
        }
        .bc-icon-btn:hover { background: var(--accent-weak); }
        .bc-icon-btn.accent { background: var(--accent); color:#fff; border-color: var(--accent); }
        .bc-icon-btn.danger:hover { color:#dc2626; }
        .bc-icon-btn svg { width:1rem; height:1rem; }

        /* Banner */
        .bc-banner {
            display:flex; align-items:center; justify-content:space-between; gap:.75rem;
            background: var(--accent-weak); border-radius:.7rem; padding:.7rem .85rem;
            font-size:.8125rem; color: var(--muted); margin-top:.85rem;
        }
        .bc-banner b { color: var(--text); }

        /* Lista de productos */
        .bc-list { margin-top: .85rem; }
        .bc-prow {
            display:flex; align-items:center; justify-content:space-between; gap:.5rem;
            padding:.6rem .6rem; border-radius:.7rem; border:1px solid transparent;
        }
        .bc-prow + .bc-prow { border-top:1px solid var(--row-border); border-radius:0; }
        .bc-prow.is-sel { background: var(--accent-weak); border-color: transparent; }
        .bc-prow-l { display:flex; align-items:center; gap:.7rem; min-width:0; }
        .bc-ico {
            display:flex; align-items:center; justify-content:center; flex:none;
            width:2.1rem; height:2.1rem; border-radius:.55rem; background: var(--soft); color: var(--muted);
        }
        .bc-ico svg { width:1.25rem; height:1.25rem; }
        .bc-prow .nm { font-size:.875rem; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .bc-prow .nm .x { color: var(--accent); font-weight:700; margin-left:.3rem; font-size:.8rem; }
        .bc-prow .sub { font-size:.75rem; color: var(--muted); }
        .bc-prow-r { display:flex; align-items:center; gap:.4rem; flex:none; }

        .bc-variants { margin-top:.5rem; padding:.5rem; border-radius:.6rem; background: var(--soft); }
        .bc-vrow { display:flex; align-items:center; justify-content:space-between; gap:.5rem; font-size:.8125rem; padding:.25rem 0; }

        /* Lista de etiquetas */
        .bc-labels { margin-top:.25rem; max-height:22rem; overflow-y:auto; display:flex; flex-direction:column; gap:.5rem; padding-right:.25rem; }
        .bc-lrow { display:flex; align-items:center; justify-content:space-between; gap:.5rem; border:1px solid var(--border); border-radius:.7rem; padding:.55rem .65rem; }
        .bc-lrow .nm { font-size:.875rem; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .bc-lrow .meta { display:flex; align-items:center; gap:.4rem; margin-top:.2rem; }
        .bc-badge { background: var(--chip-bg); color: var(--chip-text); border-radius:.35rem; padding:.05rem .4rem; font-size:.625rem; font-weight:700; letter-spacing:.03em; }
        .bc-code { font-family: ui-monospace, monospace; font-size:.75rem; color: var(--muted); }
        .bc-stepper { display:flex; align-items:center; gap:.3rem; flex:none; }
        .bc-qty { width:2rem; text-align:center; font-weight:700; font-size:.875rem; }

        .bc-empty { text-align:center; color: var(--muted); font-size:.875rem; padding:1.75rem 0; }

        /* Opciones */
        .bc-opts { margin-top:1rem; padding-top:1rem; border-top:1px solid var(--border); display:flex; flex-direction:column; gap:1rem; }
        .bc-opt-row { display:flex; align-items:center; justify-content:space-between; gap:.75rem; }
        .bc-opt-label { font-size:.875rem; font-weight:600; }
        .bc-checks { display:flex; gap:1rem; }
        .bc-check { display:flex; align-items:center; gap:.4rem; font-size:.875rem; cursor:pointer; }
        .bc-check input { width:1rem; height:1rem; accent-color: var(--accent); }
        .bc-field > label { display:block; font-size:.875rem; font-weight:600; margin-bottom:.35rem; }
        .bc-grid3 { display:grid; grid-template-columns:1fr; gap:.75rem; }
        @media (min-width:640px){ .bc-grid3 { grid-template-columns: repeat(3,1fr);} }

        .bc-seg { display:inline-flex; background: var(--soft); border:1px solid var(--border); border-radius:.6rem; padding:.2rem; }
        .bc-seg button { border:0; background:transparent; color:var(--muted); cursor:pointer; padding:.4rem .9rem; font-size:.8125rem; font-weight:600; border-radius:.45rem; }
        .bc-seg button.active { background: var(--accent); color:#fff; }

        .bc-note { background:#fff7ed; color:#9a3412; border-radius:.6rem; padding:.7rem .85rem; font-size:.75rem; }
        :is(.dark) .bc-note { background: rgba(249,115,22,.12); color:#fdba74; }

        /* Vista previa */
        .bc-preview-grid { display:flex; flex-wrap:wrap; gap:.75rem; margin-top:.25rem; }
        .bc-label {
            display:flex; flex-direction:column; align-items:center; justify-content:center;
            overflow:hidden; text-align:center; background:#fff; color:#111827;
            border:1px dashed #cbd5e1; border-radius:4px; padding:1.2mm;
        }
        .bc-label .name { font-size:2.2mm; font-weight:600; line-height:1.05; max-height:5mm; overflow:hidden; width:100%; }
        .bc-label svg { width:100%; height:auto; }
        .bc-label .below { font-size:2mm; font-family:ui-monospace,monospace; letter-spacing:.3mm; margin-top:.3mm; }
        .bc-label .price { font-size:2.6mm; font-weight:700; margin-top:.3mm; }
        .bc-more { margin-top:.75rem; font-size:.75rem; color:var(--muted); }
    </style>

    <div class="bc">
        <div class="bc-cols">

            {{-- ================= PRODUCTOS ================= --}}
            <section class="bc-card">
                <div class="bc-head">
                    <div>
                        <h2 class="bc-title">Productos</h2>
                        <p class="bc-subtitle">Buscá o escaneá un código: con el lector, el producto se agrega solo.</p>
                    </div>
                    <button type="button" class="bc-btn ghost" wire:click="addPage">
                        @svg('heroicon-o-document-plus', '', ['style' => 'width:1rem;height:1rem'])
                        Agregar esta página
                    </button>
                </div>

                <div class="bc-body">
                    <div class="bc-input-icon">
                        @svg('heroicon-o-qr-code', '', ['style' => 'width:1.15rem;height:1.15rem'])
                        <input type="text" class="bc-input" wire:model.live.debounce.400ms="search"
                            placeholder="Buscá por nombre o escaneá un código">
                    </div>

                    @if ($this->productsWithoutBarcode > 0)
                        <div class="bc-banner">
                            <span><b>{{ $this->productsWithoutBarcode }}</b>
                                {{ $this->productsWithoutBarcode === 1 ? 'producto no tiene código de barras y no aparece' : 'productos no tienen código de barras y no aparecen' }} en esta lista.</span>
                            <button type="button" class="bc-btn" wire:click="generarCodigos">
                                @svg('heroicon-o-bars-4', '', ['style' => 'width:1rem;height:1rem'])
                                Generar códigos
                            </button>
                        </div>
                    @endif

                    <div class="bc-list">
                        @forelse ($this->products as $product)
                            @php
                                $count = $product->variants->count();
                                $selectedQty = collect($selected)
                                    ->whereIn('variant_id', $product->variants->pluck('id')->all())
                                    ->sum('quantity');
                            @endphp
                            <div wire:key="prod-{{ $product->id }}">
                                <div class="bc-prow {{ $selectedQty > 0 ? 'is-sel' : '' }}">
                                    <div class="bc-prow-l">
                                        <span class="bc-ico">@svg('heroicon-o-cube')</span>
                                        <div style="min-width:0">
                                            <p class="nm">{{ $product->name }}@if ($selectedQty > 0)<span class="x">×{{ $selectedQty }}</span>@endif</p>
                                            <p class="sub">{{ $count }} {{ $count === 1 ? 'variante' : 'variantes' }}</p>
                                        </div>
                                    </div>
                                    <div class="bc-prow-r">
                                        @if ($count > 1)
                                            <button type="button" class="bc-btn link" wire:click="toggleExpand({{ $product->id }})">
                                                Ver @svg('heroicon-m-chevron-right', '', ['style' => 'width:1rem;height:1rem'])
                                            </button>
                                        @endif
                                        <button type="button" class="bc-icon-btn accent" wire:click="addProductAll({{ $product->id }})" title="Agregar">
                                            @svg('heroicon-m-plus')
                                        </button>
                                    </div>
                                </div>

                                @if ($expandedProduct === $product->id && $count > 1)
                                    <div class="bc-variants">
                                        @foreach ($product->variants as $variant)
                                            <div wire:key="var-{{ $variant->id }}" class="bc-vrow">
                                                <span>{{ trim(($variant->size ?? '').' '.($variant->color ?? '')) ?: ($variant->name ?: 'Variante') }}
                                                    <span style="color:var(--muted)">· stock {{ $variant->stock_quantity }}</span></span>
                                                <button type="button" class="bc-icon-btn accent" wire:click="addVariant({{ $variant->id }})" title="Agregar">
                                                    @svg('heroicon-m-plus')
                                                </button>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @empty
                            <p class="bc-empty">No se encontraron productos.</p>
                        @endforelse
                    </div>
                </div>
            </section>

            {{-- ================= ETIQUETAS ================= --}}
            <section class="bc-card">
                <div class="bc-head">
                    <div>
                        <h2 class="bc-title">Etiquetas <span class="bc-count">{{ $total }}</span></h2>
                        <p class="bc-subtitle">Hasta {{ $max }} etiquetas por impresión.</p>
                    </div>
                    <div class="bc-prow-r">
                        <button type="button" class="bc-btn link" wire:click="segunStock" @disabled(empty($selected))>
                            @svg('heroicon-o-squares-2x2', '', ['style' => 'width:1rem;height:1rem']) Según stock
                        </button>
                        <button type="button" class="bc-icon-btn danger" wire:click="limpiar" @disabled(empty($selected)) title="Limpiar">
                            @svg('heroicon-o-trash')
                        </button>
                    </div>
                </div>

                <div class="bc-body">
                    @if (empty($selected))
                        <p class="bc-empty">Agregá productos desde el panel de la izquierda.</p>
                    @else
                        <div class="bc-labels">
                            @foreach ($selected as $item)
                                <div wire:key="sel-{{ $item['variant_id'] }}" class="bc-lrow">
                                    <div style="min-width:0">
                                        <p class="nm">{{ $item['name'] }}</p>
                                        <div class="meta">
                                            <span class="bc-badge">EAN-13</span>
                                            <span class="bc-code">{{ $item['code'] }}</span>
                                        </div>
                                    </div>
                                    <div class="bc-stepper">
                                        <button type="button" class="bc-icon-btn" wire:click="decrement({{ $item['variant_id'] }})">@svg('heroicon-m-minus')</button>
                                        <span class="bc-qty">{{ $item['quantity'] }}</span>
                                        <button type="button" class="bc-icon-btn" wire:click="increment({{ $item['variant_id'] }})">@svg('heroicon-m-plus')</button>
                                        <button type="button" class="bc-icon-btn danger" wire:click="remove({{ $item['variant_id'] }})">@svg('heroicon-m-x-mark')</button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <div class="bc-opts">
                        <div class="bc-opt-row">
                            <span class="bc-opt-label">Mostrar en la etiqueta</span>
                            <div class="bc-checks">
                                <label class="bc-check"><input type="checkbox" wire:model.live="showName"> Nombre</label>
                                <label class="bc-check"><input type="checkbox" wire:model.live="showPrice"> Precio</label>
                            </div>
                        </div>

                        <div class="bc-field">
                            <label>Debajo de las barras</label>
                            <div class="bc-seg">
                                <button type="button" class="{{ $belowBars === 'code' ? 'active' : '' }}" wire:click="$set('belowBars', 'code')">El código</button>
                                <button type="button" class="{{ $belowBars === 'name' ? 'active' : '' }}" wire:click="$set('belowBars', 'name')">El nombre</button>
                            </div>
                        </div>

                        <div class="bc-grid3">
                            <div class="bc-field">
                                <label>Impresora</label>
                                <select class="bc-select" wire:model.live="printer">
                                    <option value="normal">Hoja normal</option>
                                    <option value="thermal">Térmica de etiquetas</option>
                                </select>
                            </div>
                            <div class="bc-field">
                                <label>Ancho (mm)</label>
                                <input type="number" min="20" max="100" class="bc-input" wire:model.live="labelWidth">
                            </div>
                            <div class="bc-field">
                                <label>Alto (mm)</label>
                                <input type="number" min="10" max="100" class="bc-input" wire:model.live="labelHeight">
                            </div>
                        </div>

                        @if (($printer ?? 'normal') === 'normal')
                            <div class="bc-field">
                                <label>Etiquetas por fila</label>
                                <input type="number" min="1" max="6" class="bc-input" wire:model.live="perRow">
                            </div>
                        @else
                            <p class="bc-note">Para la térmica: papel {{ $labelWidth }}×{{ $labelHeight }} mm, márgenes "ninguno", escala 100% y sin encabezados ni pies de página.</p>
                        @endif

                        <button type="button" class="bc-btn bc-print" wire:click="imprimir" @disabled(empty($selected))>
                            @svg('heroicon-o-printer', '', ['style' => 'width:1.1rem;height:1.1rem'])
                            Imprimir etiquetas
                        </button>
                    </div>
                </div>
            </section>
        </div>

        {{-- ================= VISTA PREVIA ================= --}}
        <section class="bc-card" style="margin-top:1.25rem">
            <h2 class="bc-title">Vista previa</h2>
            <p class="bc-subtitle">Así se verán las etiquetas con las opciones elegidas.</p>

            <div class="bc-body">
                @if (empty($this->previewLabels))
                    <p class="bc-empty">Agregá productos para ver la vista previa de sus etiquetas.</p>
                @else
                    <div class="bc-preview-grid">
                        @foreach ($this->previewLabels as $label)
                            <div class="bc-label" style="width: {{ $labelWidth }}mm; height: {{ $labelHeight }}mm;">
                                @if ($showName)<div class="name">{{ $label['name'] }}</div>@endif
                                {!! $label['svg'] !!}
                                <div class="below">{{ $belowBars === 'name' ? $label['name'] : $label['code'] }}</div>
                                @if ($showPrice)<div class="price">L {{ number_format($label['price'], 2) }}</div>@endif
                            </div>
                        @endforeach
                    </div>
                    @if ($total > count($this->previewLabels))
                        <p class="bc-more">Mostrando {{ count($this->previewLabels) }} de {{ $total }} etiquetas. Al imprimir se generan todas.</p>
                    @endif
                @endif
            </div>
        </section>
    </div>
</x-filament-panels::page>
