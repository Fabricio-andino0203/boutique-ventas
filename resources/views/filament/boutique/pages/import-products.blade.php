<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">Descargar plantilla de ejemplo</x-slot>
            <x-slot name="description">
                Archivo Excel con las columnas necesarias, datos de ejemplo e instrucciones de cada campo.
            </x-slot>

            <x-filament::button
                wire:click="descargarPlantilla"
                icon="heroicon-o-arrow-down-tray"
                color="gray"
            >
                Descargar plantilla
            </x-filament::button>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Subir archivo</x-slot>
            <x-slot name="description">Seleccioná el archivo Excel a importar.</x-slot>

            <form wire:submit="importar" class="space-y-4">
                {{ $this->form }}

                <x-filament::button type="submit" icon="heroicon-o-arrow-up-tray">
                    Importar productos
                </x-filament::button>
            </form>
        </x-filament::section>

        @if ($resultado)
            <x-filament::section>
                <x-slot name="heading">Resultado de la importación</x-slot>

                <p class="text-sm font-medium text-success-600 dark:text-success-400">
                    {{ $resultado['created'] }} producto(s) creado(s) correctamente.
                </p>

                @if (count($resultado['errors']))
                    <p class="mt-3 text-sm font-medium text-danger-600 dark:text-danger-400">
                        {{ count($resultado['errors']) }} fila(s) con error:
                    </p>
                    <ul class="mt-1 list-disc space-y-1 pl-5 text-sm text-danger-600 dark:text-danger-400">
                        @foreach ($resultado['errors'] as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                @endif
            </x-filament::section>
        @endif

        <x-filament::section>
            <x-slot name="heading">Instrucciones</x-slot>
            <ul class="list-disc space-y-1 pl-5 text-sm text-gray-600 dark:text-gray-400">
                <li>Las columnas <strong>Nombre</strong> y <strong>Precio</strong> son obligatorias.</li>
                <li>Reconocemos el formato por las columnas; no hace falta indicar cuál es.</li>
                <li>Con variantes: escribí la opción en <strong>Opcion 1</strong> (ej. "Color") y sus valores separados por coma en <strong>Valores Opcion 1</strong> (ej. "Rojo, Azul, Negro"). Hasta 3 opciones; se crea una variante por combinación.</li>
                <li>Las <strong>categorías</strong> (y subcategorías) deben existir previamente. La marca y el proveedor se crean si no existen.</li>
                <li>Booleanos aceptan: <strong>SI, SÍ, TRUE, 1</strong>. Impuesto: <strong>none / included / additional</strong>.</li>
                <li>Revisá la hoja <strong>Instrucciones</strong> de la plantilla para el detalle de cada columna.</li>
            </ul>
        </x-filament::section>
    </div>
</x-filament-panels::page>
