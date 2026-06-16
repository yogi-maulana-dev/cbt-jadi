<?php

namespace Tests\Feature;

use App\Filament\Resources\TestAttemptResource\Pages\RiwayatPelanggaran;
use App\Models\MataPelajaran;
use App\Models\PelanggaranLog;
use App\Models\Test;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class PelanggaranLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_blokir_dan_buka_blokir_mencatat_riwayat_berkali_kali(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $mapel = MataPelajaran::create(['nama' => 'Matematika', 'kode' => 'MTK']);
        $test = Test::create(['mata_pelajaran_id' => $mapel->id, 'judul' => 'UH', 'durasi' => 60, 'status' => 'published']);
        $siswa = User::factory()->create(['role' => 'siswa']);

        // Insiden 1
        $siswa->blokir('3x keluar tab', $test->id);
        $this->assertDatabaseHas('pelanggaran_logs', [
            'user_id' => $siswa->id, 'test_id' => $test->id, 'dibuka_pada' => null,
        ]);

        $siswa->fresh()->bukaBlokir();
        $log1 = PelanggaranLog::where('user_id', $siswa->id)->latest('id')->first();
        $this->assertNotNull($log1->dibuka_pada);
        $this->assertSame($admin->id, $log1->dibuka_oleh);

        // Insiden 2 (berkali-kali tersimpan)
        $siswa->fresh()->blokir('2x keluar tab', $test->id);
        $siswa->fresh()->bukaBlokir();

        $this->assertSame(2, PelanggaranLog::where('user_id', $siswa->id)->count());
        $this->assertSame(2, PelanggaranLog::where('user_id', $siswa->id)->whereNotNull('dibuka_pada')->count());
    }

    public function test_halaman_riwayat_dan_export(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $mapel = MataPelajaran::create(['nama' => 'Matematika', 'kode' => 'MTK']);
        $test = Test::create(['mata_pelajaran_id' => $mapel->id, 'judul' => 'UH', 'durasi' => 60, 'status' => 'published']);
        $siswa = User::factory()->create(['role' => 'siswa', 'name' => 'Budi']);
        $siswa->blokir('3x keluar tab', $test->id);
        $siswa->fresh()->bukaBlokir();

        $page = Livewire::actingAs($admin)->test(RiwayatPelanggaran::class, ['test' => (string) $test->id])
            ->assertSuccessful()
            ->assertSee('Budi')
            ->assertSee('3x keluar tab');

        $this->assertInstanceOf(StreamedResponse::class, $page->instance()->exportExcel());
    }
}
