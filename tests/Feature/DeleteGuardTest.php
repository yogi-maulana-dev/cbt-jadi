<?php

namespace Tests\Feature;

use App\Models\MataPelajaran;
use App\Models\Question;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeleteGuardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Test, 1: Question}
     */
    private function skenario(): array
    {
        $mapel = MataPelajaran::create(['nama' => 'Matematika', 'kode' => 'MTK']);
        $q = Question::create([
            'mata_pelajaran_id' => $mapel->id, 'tipe' => 'pilihan_ganda',
            'pertanyaan' => 'Soal ujian', 'bobot' => 1, 'tingkat_kesulitan' => 'sedang',
        ]);
        $test = Test::create([
            'mata_pelajaran_id' => $mapel->id, 'judul' => 'Ujian Uji', 'durasi' => 60, 'status' => 'published',
        ]);
        $test->questions()->attach($q->id, ['urutan' => 1, 'bobot' => 1]);

        return [$test, $q];
    }

    private function attempt(Test $test, string $status, ?\DateTimeInterface $deadline): void
    {
        $siswa = User::factory()->create(['role' => 'siswa']);
        TestAttempt::create([
            'test_id' => $test->id, 'user_id' => $siswa->id,
            'waktu_mulai' => now()->subMinutes(10), 'deadline' => $deadline, 'status' => $status,
        ]);
    }

    public function test_ujian_dan_soal_tidak_bisa_dihapus_saat_ada_siswa_mengerjakan(): void
    {
        [$test, $q] = $this->skenario();
        $this->attempt($test, 'sedang_dikerjakan', now()->addHour()); // aktif

        $this->assertTrue($test->hasActiveAttempts());
        $this->assertTrue($q->inActiveExam());

        // Hapus ujian -> diblokir.
        try {
            $test->delete();
            $this->fail('Ujian seharusnya tidak bisa dihapus.');
        } catch (\RuntimeException $e) {
            // diharapkan
        }
        $this->assertDatabaseHas('tests', ['id' => $test->id]);

        // Hapus soal -> diblokir (tidak ter-soft-delete).
        try {
            $q->delete();
            $this->fail('Soal seharusnya tidak bisa dihapus.');
        } catch (\RuntimeException $e) {
            // diharapkan
        }
        $this->assertDatabaseHas('questions', ['id' => $q->id, 'deleted_at' => null]);
    }

    public function test_bisa_dihapus_saat_attempt_selesai_atau_kadaluarsa(): void
    {
        [$test, $q] = $this->skenario();
        $this->attempt($test, 'selesai', now()->subHour());            // selesai
        $this->attempt($test, 'sedang_dikerjakan', now()->subMinute()); // kadaluarsa (deadline lewat)

        $this->assertFalse($test->hasActiveAttempts());
        $this->assertFalse($q->inActiveExam());

        // Soal (soft delete) berhasil.
        $q->delete();
        $this->assertSoftDeleted('questions', ['id' => $q->id]);

        // Ujian berhasil dihapus (attempt ikut cascade).
        $test->delete();
        $this->assertDatabaseMissing('tests', ['id' => $test->id]);
    }
}
