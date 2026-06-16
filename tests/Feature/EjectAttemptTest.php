<?php

namespace Tests\Feature;

use App\Enums\AttemptStatus;
use App\Models\Choice;
use App\Models\MataPelajaran;
use App\Models\Question;
use App\Models\Test;
use App\Models\User;
use App\Services\ExamSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EjectAttemptTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_attempt_lalu_siswa_bisa_ujian_lagi_dengan_soal_terbaru(): void
    {
        $mapel = MataPelajaran::create(['nama' => 'Matematika', 'kode' => 'MTK']);
        $q1 = Question::create([
            'mata_pelajaran_id' => $mapel->id, 'tipe' => 'pilihan_ganda',
            'pertanyaan' => 'Soal yang keliru', 'bobot' => 1, 'tingkat_kesulitan' => 'sedang',
        ]);
        Choice::create(['question_id' => $q1->id, 'label' => 'A', 'teks' => '1', 'urutan' => 1, 'is_correct' => true]);

        $test = Test::create([
            'mata_pelajaran_id' => $mapel->id, 'judul' => 'Ujian', 'durasi' => 60, 'status' => 'published',
        ]);
        $test->questions()->attach($q1->id, ['urutan' => 1, 'bobot' => 1, 'cadangan' => false]);

        $siswa = User::factory()->create(['role' => 'siswa']);
        $service = app(ExamSessionService::class);

        // Siswa mulai ujian (snapshot 1 soal).
        $a1 = $service->startOrResume($test, $siswa->id);
        $a1->answers()->create(['question_id' => $q1->id]); // ada jawaban
        $this->assertSame(AttemptStatus::SedangDikerjakan, $a1->status);
        $this->assertSame(1, $a1->attemptQuestions()->count());

        // --- Admin "Keluarkan & reset" (hapus attempt) ---
        $a1->delete();
        $this->assertDatabaseMissing('test_attempts', ['id' => $a1->id]);
        $this->assertDatabaseMissing('user_answers', ['test_attempt_id' => $a1->id]); // cascade

        // Soal diperbaiki + ditambah satu soal lagi.
        $q1->update(['pertanyaan' => 'Soal sudah diperbaiki']);
        $q2 = Question::create([
            'mata_pelajaran_id' => $mapel->id, 'tipe' => 'pilihan_ganda',
            'pertanyaan' => 'Soal tambahan', 'bobot' => 1, 'tingkat_kesulitan' => 'sedang',
        ]);
        $test->questions()->attach($q2->id, ['urutan' => 2, 'bobot' => 1, 'cadangan' => false]);

        // Siswa mulai lagi -> attempt BARU dengan soal terbaru (2 soal).
        $a2 = $service->startOrResume($test, $siswa->id);
        $this->assertNotSame($a1->id, $a2->id);
        $this->assertSame(2, $a2->attemptQuestions()->count());
    }

    public function test_buka_ruang_ujian_attempt_yang_sudah_direset_dialihkan_bukan_404(): void
    {
        $siswa = User::factory()->create(['role' => 'siswa']);

        // attempt id yang tidak ada (sudah dihapus/direset) -> halaman pemberitahuan
        $this->actingAs($siswa)
            ->get('/ujian/attempt/999999')
            ->assertRedirect(route('exam.kicked'));
    }
}
