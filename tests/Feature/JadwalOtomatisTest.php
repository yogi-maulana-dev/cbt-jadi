<?php

namespace Tests\Feature;

use App\Models\MataPelajaran;
use App\Models\Ruangan;
use App\Models\Test;
use App\Models\User;
use App\Services\JadwalOtomatisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JadwalOtomatisTest extends TestCase
{
    use RefreshDatabase;

    private function ujian(array $attr = []): Test
    {
        $mapel = MataPelajaran::create(['nama' => 'Matematika', 'kode' => 'MTK']);

        return Test::create(array_merge([
            'mata_pelajaran_id' => $mapel->id, 'judul' => 'UH 1',
            'durasi' => 60, 'status' => 'published', 'token' => 'TOK123',
        ], $attr));
    }

    public function test_membagi_siswa_dan_pengawas_sesuai_kapasitas(): void
    {
        // Reset ruangan baku, sediakan 2 ruang kapasitas 2.
        Ruangan::query()->delete();
        $r1 = Ruangan::create(['nama' => 'Ruang 1', 'kapasitas' => 2]);
        $r2 = Ruangan::create(['nama' => 'Ruang 2', 'kapasitas' => 2]);

        // 3 siswa -> 2 di Ruang 1, 1 di Ruang 2.
        User::factory()->count(3)->create(['role' => 'siswa']);
        // 2 pengawas tersedia.
        User::factory()->count(2)->create(['role' => 'pengawas', 'aktif' => true]);

        $test = $this->ujian();
        $report = app(JadwalOtomatisService::class)->generate($test);

        $this->assertSame(3, $report['siswa']);
        $this->assertSame(2, $report['ruangan']);
        $this->assertSame(2, $report['pengawas']);
        $this->assertEmpty($report['warnings']);

        $this->assertSame(2, $test->penempatanSiswa()->where('ruangan_id', $r1->id)->count());
        $this->assertSame(1, $test->penempatanSiswa()->where('ruangan_id', $r2->id)->count());

        // Tiap ruangan terpakai punya pengawas.
        $this->assertTrue($test->pengawas()->wherePivot('ruangan', 'Ruang 1')->exists());
        $this->assertTrue($test->pengawas()->wherePivot('ruangan', 'Ruang 2')->exists());
    }

    public function test_peringatan_kapasitas_kurang_dan_pengawas_kurang(): void
    {
        Ruangan::query()->delete();
        Ruangan::create(['nama' => 'Ruang 1', 'kapasitas' => 2]);

        User::factory()->count(3)->create(['role' => 'siswa']); // 1 tak tertampung
        // tidak ada pengawas tersedia

        $test = $this->ujian();
        $report = app(JadwalOtomatisService::class)->generate($test);

        $this->assertSame(2, $report['siswa']);
        $this->assertSame(0, $report['pengawas']);
        $this->assertNotEmpty($report['warnings']);
        // Ada peringatan siswa tak tertampung & ruang tanpa pengawas.
        $this->assertTrue(collect($report['warnings'])->contains(fn ($w) => str_contains($w, 'belum kebagian ruangan')));
        $this->assertTrue(collect($report['warnings'])->contains(fn ($w) => str_contains($w, 'belum dapat pengawas')));
    }

    public function test_generate_ulang_menata_ulang_penempatan(): void
    {
        Ruangan::query()->delete();
        Ruangan::create(['nama' => 'Ruang 1', 'kapasitas' => 50]);
        User::factory()->count(2)->create(['role' => 'siswa']);

        $test = $this->ujian();
        $svc = app(JadwalOtomatisService::class);
        $svc->generate($test, tempatkanSiswa: true, tugaskanPengawas: false);
        $svc->generate($test, tempatkanSiswa: true, tugaskanPengawas: false);

        // Tidak menggandakan: tetap 2 penempatan.
        $this->assertSame(2, $test->penempatanSiswa()->count());
    }
}
