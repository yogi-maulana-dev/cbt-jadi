<?php

namespace Tests\Feature;

use App\Filament\Resources\JurusanResource;
use App\Filament\Resources\MataPelajaranResource;
use App\Filament\Resources\QuestionResource;
use App\Filament\Resources\SiswaDiblokirResource;
use App\Filament\Resources\TestAttemptResource;
use App\Filament\Resources\TestResource;
use App\Filament\Resources\TestAttemptResource\Pages\ListTestAttempts;
use App\Filament\Resources\TestResource\Pages\ListTests;
use App\Filament\Resources\UserResource;
use App\Models\MataPelajaran;
use App\Models\Question;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_siswa_tidak_bisa_akses_panel_admin(): void
    {
        $panel = Filament::getPanel('admin');

        $this->assertFalse(User::factory()->create(['role' => 'siswa'])->canAccessPanel($panel));
        $this->assertTrue(User::factory()->create(['role' => 'guru'])->canAccessPanel($panel));
        $this->assertTrue(User::factory()->create(['role' => 'operator'])->canAccessPanel($panel));
        $this->assertTrue(User::factory()->create(['role' => 'admin'])->canAccessPanel($panel));
    }

    public function test_menu_guru_hanya_bank_soal_dan_hasil_ujian(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'guru']));

        $this->assertTrue(QuestionResource::canViewAny());     // Bank Soal
        $this->assertTrue(TestAttemptResource::canViewAny());  // Hasil Ujian

        $this->assertFalse(TestResource::canViewAny());        // Ujian -> tidak
        $this->assertFalse(SiswaDiblokirResource::canViewAny());
        $this->assertFalse(UserResource::canViewAny());
        $this->assertFalse(MataPelajaranResource::canViewAny());
    }

    public function test_menu_operator_hanya_jadwal_ujian_dan_buka_blokir(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'operator']));

        $this->assertTrue(TestResource::canViewAny());          // Ujian (jadwal)
        $this->assertTrue(SiswaDiblokirResource::canViewAny()); // Buka blokir

        $this->assertFalse(QuestionResource::canViewAny());     // Bank Soal -> tidak
        $this->assertFalse(TestAttemptResource::canViewAny());  // Hasil Ujian -> tidak
        $this->assertFalse(UserResource::canViewAny());

        // Operator hanya MELIHAT ujian (read-only).
        $this->assertFalse(TestResource::canCreate());
        $this->assertFalse(TestResource::canEdit(new \App\Models\Test()));
    }

    public function test_admin_akses_penuh(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->assertTrue(UserResource::canViewAny());
        $this->assertTrue(MataPelajaranResource::canViewAny());
        $this->assertTrue(JurusanResource::canViewAny());
        $this->assertTrue(QuestionResource::canViewAny());
        $this->assertTrue(TestResource::canViewAny());
        $this->assertTrue(TestResource::canCreate());
    }

    public function test_operator_melihat_jadwal_ujian_tanpa_tombol_aksi(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $op = User::factory()->create(['role' => 'operator']);
        $mapel = MataPelajaran::create(['nama' => 'Matematika', 'kode' => 'MTK']);
        $test = Test::create(['mata_pelajaran_id' => $mapel->id, 'judul' => 'UH', 'durasi' => 60, 'status' => 'published']);

        Livewire::actingAs($op)
            ->test(ListTests::class)
            ->assertCanSeeTableRecords([$test])          // bisa melihat jadwal
            ->assertActionHidden('create')               // tak ada tombol "New"
            ->assertTableActionHidden('edit', $test);    // tak ada tombol "Edit"
    }

    public function test_guru_lihat_hasil_ujian_tanpa_tombol_aksi(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $mapel = MataPelajaran::create(['nama' => 'Matematika', 'kode' => 'MTK']);
        $guru = User::factory()->create(['role' => 'guru']);
        $guru->mataPelajarans()->attach($mapel->id);

        $test = Test::create(['mata_pelajaran_id' => $mapel->id, 'judul' => 'UH', 'durasi' => 60, 'status' => 'published']);
        $siswa = User::factory()->create(['role' => 'siswa']);
        $attempt = TestAttempt::create([
            'test_id' => $test->id, 'user_id' => $siswa->id,
            'waktu_mulai' => now(), 'deadline' => now()->addHour(), 'status' => 'sedang_dikerjakan',
        ]);

        Livewire::actingAs($guru)
            ->test(ListTestAttempts::class, ['test' => (string) $test->id])
            ->assertCanSeeTableRecords([$attempt])              // bisa melihat hasil
            ->assertTableActionHidden('keluarkan', $attempt)    // tak ada tombol Keluarkan
            ->assertTableActionHidden('edit', $attempt);        // tak ada tombol Edit
    }

    public function test_guru_hanya_melihat_bank_soal_mapel_yang_diampu(): void
    {
        $mtk = MataPelajaran::create(['nama' => 'Matematika', 'kode' => 'MTK']);
        $ipa = MataPelajaran::create(['nama' => 'IPA', 'kode' => 'IPA']);

        $guru = User::factory()->create(['role' => 'guru']);
        $guru->mataPelajarans()->attach($mtk->id);

        Question::create(['mata_pelajaran_id' => $mtk->id, 'tipe' => 'pilihan_ganda', 'pertanyaan' => 'Soal MTK', 'bobot' => 1, 'tingkat_kesulitan' => 'sedang']);
        Question::create(['mata_pelajaran_id' => $ipa->id, 'tipe' => 'pilihan_ganda', 'pertanyaan' => 'Soal IPA', 'bobot' => 1, 'tingkat_kesulitan' => 'sedang']);

        $this->actingAs($guru);
        $soal = QuestionResource::getEloquentQuery()->pluck('pertanyaan')->all();

        $this->assertContains('Soal MTK', $soal);
        $this->assertNotContains('Soal IPA', $soal);
    }
}
