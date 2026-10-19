@php
    use Illuminate\Support\Facades\Storage;
    $mediaDisk = config('filesystems.media');
    $progress = $this->progress;
    $pct = $progress['total'] > 0 ? round($progress['withImage'] / $progress['total'] * 100) : 0;
    $info = $this->pageInfo;
@endphp

<x-filament-panels::page>
    {{-- UI estilizada con CSS propio (el panel no carga app.css). --}}
    <style>
        .pi {
            --card-bg:#fff; --border:#e5e7eb; --text:#0f172a; --muted:#64748b;
            --soft:#f8fafc; --accent:#F43F78; --accent-weak:#fde7ef; --accent-text:#be185d;
            --drop-bg:#f5f3ff; --drop-border:#c7d2fe; --drop-text:#6366f1;
            --input-bg:#fff; --input-border:#d1d5db; --track:#e5e7eb;
        }
        :is(.dark) .pi {
            --card-bg:#1b2431; --border:rgba(255,255,255,.08); --text:#f1f5f9; --muted:#94a3b8;
            --soft:rgba(255,255,255,.035); --accent:#F43F78; --accent-weak:rgba(244,63,120,.15); --accent-text:#f9a8c4;
            --drop-bg:rgba(99,102,241,.1); --drop-border:rgba(129,140,248,.4); --drop-text:#a5b4fc;
            --input-bg:rgba(255,255,255,.04); --input-border:rgba(255,255,255,.14); --track:rgba(255,255,255,.1);
        }
        .pi { color: var(--text); }
        .pi * { box-sizing: border-box; }

        /* Barra superior */
        .pi-bar { display:flex; align-items:center; gap:1rem; flex-wrap:wrap; }
        .pi-search { position:relative; flex:1; min-width:220px; }
        .pi-search svg { position:absolute; left:.75rem; top:50%; transform:translateY(-50%); color:var(--muted); width:1.1rem; height:1.1rem; }
        .pi-search input {
            width:100%; background:var(--input-bg); color:var(--text); border:1px solid var(--input-border);
            border-radius:.7rem; padding:.6rem .9rem .6rem 2.4rem; font-size:.875rem; outline:none;
        }
        .pi-search input:focus { border-color:var(--accent); box-shadow:0 0 0 2px var(--accent-weak); }
        .pi-check { display:flex; align-items:center; gap:.45rem; font-size:.875rem; color:var(--muted); cursor:pointer; white-space:nowrap; }
        .pi-check input { width:1rem; height:1rem; accent-color:var(--accent); }
        .pi-prog { display:flex; align-items:center; gap:.75rem; flex:1; min-width:200px; }
        .pi-track { flex:1; height:.55rem; background:var(--track); border-radius:999px; overflow:hidden; }
        .pi-fill { height:100%; background:var(--accent); border-radius:999px; transition:width .3s; }
        .pi-count { font-size:.8125rem; color:var(--muted); white-space:nowrap; }
        .pi-count b { color:var(--text); }

        /* Grilla de productos */
        .pi-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:1rem; margin-top:1.25rem; }
        @media (min-width:640px){ .pi-grid { grid-template-columns:repeat(3,1fr);} }
        @media (min-width:1024px){ .pi-grid { grid-template-columns:repeat(4,1fr);} }
        @media (min-width:1280px){ .pi-grid { grid-template-columns:repeat(6,1fr);} }

        .pi-card { background:var(--card-bg); border:1px solid var(--border); border-radius:.9rem; overflow:hidden; display:flex; flex-direction:column; box-shadow:0 1px 2px rgba(15,23,42,.04); }
        .pi-imgwrap { position:relative; aspect-ratio:1/1; background:var(--soft); }
        .pi-imgwrap img { width:100%; height:100%; object-fit:cover; display:block; cursor:pointer; }
        .pi-badge { position:absolute; right:.5rem; top:.5rem; border-radius:999px; padding:.1rem .5rem; font-size:.6875rem; font-weight:600; }
        .pi-badge.on { background:#dcfce7; color:#15803d; }
        .pi-badge.off { background:#e2e8f0; color:#64748b; }
        :is(.dark) .pi-badge.on { background:rgba(34,197,94,.2); color:#86efac; }
        :is(.dark) .pi-badge.off { background:rgba(255,255,255,.1); color:#cbd5e1; }

        .pi-drop {
            display:flex; flex-direction:column; align-items:center; justify-content:center; gap:.5rem;
            height:100%; width:100%; cursor:pointer; background:var(--drop-bg);
            border:2px dashed var(--drop-border); color:var(--drop-text); font-size:.8125rem; font-weight:600;
        }
        .pi-drop:hover { filter:brightness(.98); }
        .pi-drop svg { width:2rem; height:2rem; }

        .pi-body { padding:.75rem .85rem .85rem; display:flex; flex-direction:column; gap:.3rem; }
        .pi-name { font-size:.9rem; font-weight:700; line-height:1.2; }
        .pi-type { display:inline-flex; align-items:center; gap:.3rem; font-size:.8125rem; color:var(--muted); background:transparent; border:0; padding:0; cursor:pointer; }
        .pi-type.plain { cursor:default; }
        .pi-type svg { width:1rem; height:1rem; }

        .pi-empty { text-align:center; color:var(--muted); font-size:.875rem; padding:2.5rem 0; }

        /* Paginación */
        .pi-pager { display:flex; align-items:center; justify-content:center; gap:.4rem; margin-top:1.5rem; font-size:.875rem; color:var(--muted); }
        .pi-pager .lbl { margin-right:.5rem; }
        .pi-pg { display:inline-flex; align-items:center; justify-content:center; min-width:2rem; height:2rem; padding:0 .5rem; border-radius:.5rem; border:1px solid var(--border); background:var(--card-bg); color:var(--text); cursor:pointer; }
        .pi-pg:hover:not(:disabled) { background:var(--soft); }
        .pi-pg.active { background:var(--accent); color:#fff; border-color:var(--accent); }
        .pi-pg:disabled { opacity:.4; cursor:not-allowed; }
        .pi-pg svg { width:1rem; height:1rem; }
    </style>

    <div class="pi">
        {{-- Barra: búsqueda + solo sin imagen + progreso --}}
        <div class="pi-bar">
            <div class="pi-search">
                @svg('heroicon-o-magnifying-glass')
                <input type="text" wire:model.live.debounce.400ms="search" placeholder="Buscar producto">
            </div>
            <label class="pi-check">
                <input type="checkbox" wire:model.live="onlyWithoutImage"> Solo sin imagen
            </label>
            <div class="pi-prog">
                <div class="pi-track"><div class="pi-fill" style="width: {{ $pct }}%"></div></div>
                <span class="pi-count"><b>{{ $progress['withImage'] }}</b> de {{ $progress['total'] }} con imagen</span>
            </div>
        </div>

        @if ($this->pageProducts->isEmpty())
            <p class="pi-empty">No se encontraron productos{{ $onlyWithoutImage ? ' sin imagen' : '' }}.</p>
        @else
            <div class="pi-grid">
                @foreach ($this->pageProducts as $product)
                    @php
                        $variantImg = $product->variants->first(fn ($v) => filled($v->image_path));
                        $path = $product->image_path ?: $variantImg?->image_path;
                        $url = $path ? Storage::disk($mediaDisk)->url($path) : null;
                        $variantsWithImage = $product->variants->filter(fn ($v) => filled($v->image_path))->count();
                        $hasAny = (bool) $url;
                        $isVariable = $product->variants->count() > 1;
                    @endphp
                    <div wire:key="prod-{{ $product->id }}" class="pi-card">
                        <div class="pi-imgwrap">
                            @if ($url)
                                <img src="{{ $url }}" alt="{{ $product->name }}"
                                    wire:click="mountAction('asignarImagen', { product: {{ $product->id }} })" />
                                <span class="pi-badge on">Con imagen</span>
                            @else
                                <div class="pi-drop" wire:click="mountAction('asignarImagen', { product: {{ $product->id }} })">
                                    @svg('heroicon-o-photo')
                                    <span>Subir o arrastrar</span>
                                </div>
                                <span class="pi-badge off">Sin imagen</span>
                            @endif
                        </div>
                        <div class="pi-body">
                            <p class="pi-name">{{ $product->name }}</p>
                            @if ($isVariable)
                                <button type="button" class="pi-type"
                                    wire:click="mountAction('asignarImagen', { product: {{ $product->id }} })">
                                    Con variantes
                                    <span style="color:var(--muted)">· {{ $variantsWithImage }}/{{ $product->variants->count() }} con foto</span>
                                    @svg('heroicon-m-chevron-down')
                                </button>
                            @else
                                <span class="pi-type plain">Producto simple</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Paginación --}}
            <div class="pi-pager">
                <span class="lbl">{{ $info['from'] }}–{{ $info['to'] }} de {{ $info['total'] }} productos</span>
                <button type="button" class="pi-pg" wire:click="goToPage(1)" @disabled($page <= 1)>@svg('heroicon-m-chevron-double-left')</button>
                <button type="button" class="pi-pg" wire:click="goToPage({{ $page - 1 }})" @disabled($page <= 1)>@svg('heroicon-m-chevron-left')</button>
                @for ($p = 1; $p <= $info['lastPage']; $p++)
                    <button type="button" class="pi-pg {{ $p === $page ? 'active' : '' }}" wire:click="goToPage({{ $p }})">{{ $p }}</button>
                @endfor
                <button type="button" class="pi-pg" wire:click="goToPage({{ $page + 1 }})" @disabled($page >= $info['lastPage'])>@svg('heroicon-m-chevron-right')</button>
                <button type="button" class="pi-pg" wire:click="goToPage({{ $info['lastPage'] }})" @disabled($page >= $info['lastPage'])>@svg('heroicon-m-chevron-double-right')</button>
            </div>
        @endif
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
