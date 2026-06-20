<?php

namespace Tests\Feature;

use App\Models\Ruangan;
use App\Services\RuanganImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class RuanganImportTest extends TestCase
{
    use RefreshDatabase;

    private function buatExcel(array $rows): string
    {
        $ss = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle('Ruangan');
        $sheet->fromArray(RuanganImport::HEADERS, null, 'A1');
        $sheet->fromArray($rows, null, 'A2');

        $path = tempnam(sys_get_temp_dir(), 'ruang').'.xlsx';
        (new Xlsx($ss))->save($path);

        return $path;
    }

    public function test_import_ruangan_dengan_kapasitas(): void
    {
        $path = $this->buatExcel([
            ['Ruang 1', 40],          // perbarui (sudah ada dari seeder)
            ['Lab Komputer A', 36],   // baru
            ['', 10],                 // nama kosong -> peringatan
            ['Lab B', 'banyak'],      // kapasitas bukan angka -> dikosongkan
        ]);

        $report = app(RuanganImport::class)->import($path);
        @unlink($path);

        $this->assertSame(3, $report['imported']);
        $this->assertCount(2, $report['warnings']);

        $this->assertSame(40, Ruangan::where('nama', 'Ruang 1')->value('kapasitas'));
        $this->assertSame(36, Ruangan::where('nama', 'Lab Komputer A')->value('kapasitas'));
        $this->assertNull(Ruangan::where('nama', 'Lab B')->value('kapasitas'));

        // Tidak membuat duplikat untuk Ruang 1 (sudah ada dari seeder).
        $this->assertSame(1, Ruangan::where('nama', 'Ruang 1')->count());
    }

    public function test_pilihan_dropdown_menampilkan_kapasitas(): void
    {
        Ruangan::where('nama', 'Ruang 1')->update(['kapasitas' => 40]);

        $pilihan = Ruangan::pilihan();

        $this->assertSame('Ruang 1 (kap. 40)', $pilihan['Ruang 1']);
        $this->assertSame('Ruang 2', $pilihan['Ruang 2']); // tanpa kapasitas
    }
}
