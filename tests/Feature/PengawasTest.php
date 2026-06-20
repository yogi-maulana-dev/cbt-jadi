<?php

namespace Tests\Feature;

use App\Filament\Pages\Pengawasan;
use App\Filament\Resources\TestResource\Pages\EditTest;
use App\Filament\Resources\TestResource\RelationManagers\PengawasRelationManager;
use App\Models\MataPelajaran;
use App\Models\Test;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PengawasTest extends TestCase
{
    use RefreshDatabase;

    private function ujian(string $token = 'OLD123'): Test
    {
        $mapel = MataPelajaran::create(['nama' => 'Matematika', 'kode' => 'MTK']);

        return Test::create([
            'mata_pelajaran_id' => $mapel->id, 'judul' => 'UH 1',
            'durasi' => 60, 'status' => 'published', 'token' => $token,
        ]);
    }

    public function test_attach_pengawas_via_relation_manager_tidak_error(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $admin = User::factory()->create(['role' => 'admin']);
        $test = $this->ujian();
        $guru = User::factory()->create(['role' => 'guru', 'name' => 'Bu Ani']);

        Livewire::actingAs($admin)
            ->test(PengawasRelationManager::class, ['ownerRecord' => $test, 'pageClass' => EditTest::class])
            ->assertSuccessful()
            ->callTableAction('attach', data: ['recordId' => $guru->id, 'ruangan' => 'Ruang 10'])
            ->assertHasNoTableActionErrors();

        $this->assertTrue($test->pengawas()->whereKey($guru->id)->exists());
    }

    public function test_ruangan_tidak_boleh_sama_dalam_satu_jadwal(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $admin = User::factory()->create(['role' => 'admin']);
        $test = $this->ujian();

        $p1 = User::factory()->create(['role' => 'pengawas', 'name' => 'Pak A']);
        $test->pengawas()->attach($p1->id, ['ruangan' => 'Ruang 1']);

        $p2 = User::factory()->create(['role' => 'guru', 'name' => 'Bu B']);

        // Pakai ruangan yang sama -> harus error.
        Livewire::actingAs($admin)
            ->test(PengawasRelationManager::class, ['ownerRecord' => $test, 'pageClass' => EditTest::class])
            ->callTableAction('attach', data: ['recordId' => $p2->id, 'ruangan' => 'Ruang 1'])
            ->assertHasTableActionErrors(['ruangan']);

        $this->assertFalse($test->pengawas()->whereKey($p2->id)->exists());

        // Ruangan beda -> sukses.
        Livewire::actingAs($admin)
            ->test(PengawasRelationManager::class, ['ownerRecord' => $test, 'pageClass' => EditTest::class])
            ->callTableAction('attach', data: ['recordId' => $p2->id, 'ruangan' => 'Ruang 2'])
            ->assertHasNoTableActionErrors();

        $this->assertTrue($test->pengawas()->whereKey($p2->id)->exists());
    }

    public function test_akses_pengawasan_per_role(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'pengawas']));
        $this->assertTrue(Pengawasan::canAccess());

        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->assertTrue(Pengawasan::canAccess());

        $this->actingAs(User::factory()->create(['role' => 'guru']));
        $this->assertFalse(Pengawasan::canAccess());

        $this->actingAs(User::factory()->create(['role' => 'siswa']));
        $this->assertFalse(Pengawasan::canAccess());
    }

    public function test_pengawas_lihat_jadwalnya_dan_ganti_token(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $test = $this->ujian('OLD123');
        $pengawas = User::factory()->create(['role' => 'pengawas']);
        $test->pengawas()->attach($pengawas->id, ['ruangan' => 'Lab 1']);

        // 1 jadwal bisa banyak pengawas.
        $pengawas2 = User::factory()->create(['role' => 'pengawas']);
        $test->pengawas()->attach($pengawas2->id, ['ruangan' => 'Lab 2']);
        $this->assertSame(2, $test->pengawas()->count());

        $page = Livewire::actingAs($pengawas)->test(Pengawasan::class);
        $jadwal = $page->instance()->getJadwal();
        $this->assertCount(1, $jadwal);
        $this->assertSame('Lab 1', $jadwal->first()->pivot->ruangan);

        // Ganti token.
        $page->call('gantiToken', $test->id);
        $this->assertNotSame('OLD123', $test->fresh()->token);
    }

    public function test_satu_pengawas_bisa_banyak_jadwal_berbeda(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $mapel = MataPelajaran::create(['nama' => 'Matematika', 'kode' => 'MTK']);
        $sesi1 = Test::create(['mata_pelajaran_id' => $mapel->id, 'judul' => 'Sesi 1', 'durasi' => 60, 'status' => 'published', 'token' => 'AAA111']);
        $sesi2 = Test::create(['mata_pelajaran_id' => $mapel->id, 'judul' => 'Sesi 2', 'durasi' => 60, 'status' => 'published', 'token' => 'BBB222']);

        $pengawas = User::factory()->create(['role' => 'pengawas']);
        $sesi1->pengawas()->attach($pengawas->id, ['ruangan' => 'Ruang 1']);
        $sesi2->pengawas()->attach($pengawas->id, ['ruangan' => 'Ruang 2']);

        $jadwal = Livewire::actingAs($pengawas)->test(Pengawasan::class)->instance()->getJadwal();

        $this->assertCount(2, $jadwal); // dua jadwal berbeda
        $this->assertEqualsCanonicalizing(['Sesi 1', 'Sesi 2'], $jadwal->pluck('judul')->all());
        $this->assertEqualsCanonicalizing(['Ruang 1', 'Ruang 2'], $jadwal->pluck('pivot.ruangan')->all());
    }

    public function test_deteksi_bentrok_pengawas_dengan_nama(): void
    {
        $mapel = MataPelajaran::create(['nama' => 'Matematika', 'kode' => 'MTK']);
        $a = Test::create(['mata_pelajaran_id' => $mapel->id, 'judul' => 'Sesi A', 'durasi' => 60, 'status' => 'published', 'waktu_mulai' => '2026-06-20 08:00:00', 'waktu_selesai' => '2026-06-20 09:00:00']);
        $b = Test::create(['mata_pelajaran_id' => $mapel->id, 'judul' => 'Sesi B', 'durasi' => 60, 'status' => 'published', 'waktu_mulai' => '2026-06-20 08:30:00', 'waktu_selesai' => '2026-06-20 09:30:00']); // bentrok A
        $c = Test::create(['mata_pelajaran_id' => $mapel->id, 'judul' => 'Sesi C', 'durasi' => 60, 'status' => 'published', 'waktu_mulai' => '2026-06-20 10:00:00', 'waktu_selesai' => '2026-06-20 11:00:00']); // tak bentrok

        $p = User::factory()->create(['role' => 'pengawas', 'name' => 'Pak Budi']);
        $a->pengawas()->attach($p->id, ['ruangan' => 'R1']);
        $b->pengawas()->attach($p->id, ['ruangan' => 'R2']);
        $c->pengawas()->attach($p->id, ['ruangan' => 'R3']);

        $konflikB = $b->fresh()->konflikPengawas();
        $this->assertNotEmpty($konflikB);
        $this->assertStringContainsString('Pak Budi', $konflikB[0]);
        $this->assertStringContainsString('Sesi A', $konflikB[0]);

        // Tidak overlap -> tidak bentrok.
        $this->assertEmpty($c->fresh()->konflikPengawas());
    }

    public function test_pengawas_tidak_bisa_ganti_token_ujian_yang_tidak_diawasi(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $test = $this->ujian('KEEP01');
        $pengawas = User::factory()->create(['role' => 'pengawas']); // tidak ditugaskan

        Livewire::actingAs($pengawas)->test(Pengawasan::class)->call('gantiToken', $test->id);

        $this->assertSame('KEEP01', $test->fresh()->token);
    }
}
