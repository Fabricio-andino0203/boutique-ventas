<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ProductImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ImportarProductosTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(): Tenant
    {
        return Tenant::create(['name' => 'Comercio', 'slug' => 'c-'.uniqid()]);
    }

    private function boutiqueUser(Tenant $tenant): User
    {
        return User::create([
            'tenant_id' => $tenant->id, 'name' => 'V', 'email' => 'u'.uniqid().'@t.local',
            'password' => 'secret', 'is_super_admin' => false, 'is_active' => true,
        ]);
    }

    private function writeXlsx(array $rows): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        foreach (ProductImporter::COLUMNS as $i => $name) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($i + 1).'1', $name);
        }
        $r = 2;
        foreach ($rows as $row) {
            $sheet->fromArray($row, null, 'A'.$r);
            $r++;
        }
        $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    public function test_la_plantilla_se_genera(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'tpl').'.xlsx';
        (new Xlsx((new ProductImporter)->buildTemplate()))->save($path);

        $this->assertFileExists($path);
        $this->assertGreaterThan(1000, filesize($path));
    }

    public function test_importa_productos_simple_y_variable(): void
    {
        $tenant = $this->tenant();
        Auth::login($this->boutiqueUser($tenant));
        Category::create(['name' => 'Ropa']);

        $path = $this->writeXlsx([
            ['Pantalón', 450, 200, 8, 1, 400, '', 'Ropa', '', 'MarcaX', 'ProvX', 'Unidad', 'd', 'included', 15, 'SI', 'NO', 'NO', 'SI', '', '', '', '', '', ''],
            ['Blusa', 300, 120, 0, 1, 250, '', 'Ropa', '', 'MarcaX', 'ProvX', 'Unidad', 'd', 'none', 0, 'SI', 'NO', 'NO', 'SI', 'Talla', 'S, M', 'Color', 'Rojo, Azul', '', ''],
        ]);

        $result = (new ProductImporter)->import($path);

        $this->assertSame(2, $result['created']);
        $this->assertSame([], $result['errors']);

        $pantalon = Product::where('name', 'Pantalón')->first();
        $this->assertSame('simple', $pantalon->type);
        $this->assertSame(1, $pantalon->variants()->count());
        $this->assertSame(8, $pantalon->variants()->first()->stock_quantity);
        $this->assertNotNull($pantalon->brand_id);

        $blusa = Product::where('name', 'Blusa')->first();
        $this->assertSame('variable', $blusa->type);
        $this->assertSame(4, $blusa->variants()->count());
    }

    public function test_rechaza_categoria_inexistente(): void
    {
        $tenant = $this->tenant();
        Auth::login($this->boutiqueUser($tenant));

        $path = $this->writeXlsx([
            ['Algo', 100, 0, 0, 0, '', '', 'NoExiste', '', '', '', 'Unidad', '', 'none', 0, '', '', '', '', '', '', '', '', '', ''],
        ]);

        $result = (new ProductImporter)->import($path);

        $this->assertSame(0, $result['created']);
        $this->assertNotEmpty($result['errors']);
    }
}
