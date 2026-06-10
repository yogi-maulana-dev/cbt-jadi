<?php

namespace Tests\Feature;

use App\Enums\AttemptStatus;
use App\Livewire\Exam\ExamRoom;
use App\Models\Choice;
use App\Models\MataPelajaran;
use App\Models\Question;
use App\Models\Test;
use App\Models\User;
use App\Services\ExamSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class ExamRoomTest extends TestCase
{
    use RefreshDatabase;

    private function buatUjianTersnapshot(): array
    {
        $siswa = User::create([
            'name' => 'Siswa', 'email' => 's@t.test',
            'password' => bcrypt('x'), 'role' => 'siswa',
        ]);

        $mapel = MataPelajaran::create(['nama' => 'MTK', 'kode' => 'MTK']);

        $test = Test::create([
            'mata_pelajaran_id' => $mapel->id,
            'judul' => 'Ulangan', 'durasi' => 30,
            'kkm' => 70, 'status' => 'published',
        ]);

        // Dua soal, masing-masing 1 opsi benar.
        $kunci = [];
        foreach (range(1, 2) as $n) {
            $q = Question::create([
                'mata_pelajaran_id' => $mapel->id,
                'tipe' => 'pilihan_ganda',
                'pertanyaan' => "Soal $n", 'bobot' => 1,
                'tingkat_kesulitan' => 'sedang',
            ]);
            $benar = Choice::create(['question_id' => $q->id, 'label' => 'A', 'teks' => 'benar', 'urutan' => 1, 'is_correct' => true]);
            Choice::create(['question_id' => $q->id, 'label' => 'B', 'teks' => 'salah', 'urutan' => 2, 'is_correct' => false]);
            $test->questions()->attach($q->id, ['urutan' => $n]);
            $kunci[$q->id] = $benar->id;
        }

        $attempt = app(ExamSessionService::class)->startOrResume($test, $siswa->id);

        return [$siswa, $attempt, $kunci];
    }

    public function test_snapshot_dibuat_saat_mulai(): void
    {
        [, $attempt] = $this->buatUjianTersnapshot();

        $this->assertSame(2, $attempt->attemptQuestions()->count());
        $this->assertNotNull($attempt->deadline);
    }

    public function test_autosave_menyimpan_jawaban_dan_indikator_terupdate(): void
    {
        [$siswa, $attempt, $kunci] = $this->buatUjianTersnapshot();
        $first = $attempt->attemptQuestions()->first();

        Livewire::actingAs($siswa)
            ->test(ExamRoom::class, ['attempt' => $attempt])
            ->assertSet('attemptId', $attempt->id)
            ->call('saveAnswer', $kunci[$first->question_id])
            ->assertSet('selectedChoiceId', $kunci[$first->question_id]);

        $this->assertDatabaseHas('user_answers', [
            'test_attempt_id' => $attempt->id,
            'question_id' => $first->question_id,
            'choice_id' => $kunci[$first->question_id],
        ]);
    }

    public function test_attempt_id_terkunci_dari_tampering(): void
    {
        [$siswa, $attempt] = $this->buatUjianTersnapshot();

        // #[Locked] -> mengubah attemptId dari frontend harus dilempar.
        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::actingAs($siswa)
            ->test(ExamRoom::class, ['attempt' => $attempt])
            ->set('attemptId', 99999);
    }

    public function test_finish_menilai_otomatis_dan_redirect(): void
    {
        [$siswa, $attempt, $kunci] = $this->buatUjianTersnapshot();

        $component = Livewire::actingAs($siswa)->test(ExamRoom::class, ['attempt' => $attempt]);

        // Jawab tiap soal dengan benar: navigasi ke indeks-nya lalu simpan
        // kunci milik soal pada indeks tersebut (saveAnswer menyimpan ke soal current).
        $ordered = $attempt->attemptQuestions()->orderBy('urutan')->get();
        foreach ($ordered as $i => $aq) {
            $component->call('goTo', $i)
                ->call('saveAnswer', $kunci[$aq->question_id]);
        }

        $component->call('finish')
            ->assertRedirect(route('exam.result', $attempt->id));

        $attempt->refresh();
        $this->assertSame(AttemptStatus::Selesai, $attempt->status);
        $this->assertEquals(100.0, (float) $attempt->skor);
        $this->assertSame(2, $attempt->jumlah_benar);
    }

    public function test_toggle_flag_menandai_soal_ragu(): void
    {
        [$siswa, $attempt] = $this->buatUjianTersnapshot();
        $first = $attempt->attemptQuestions()->first();

        Livewire::actingAs($siswa)
            ->test(ExamRoom::class, ['attempt' => $attempt])
            ->call('toggleFlag');

        $this->assertTrue((bool) $first->fresh()->ragu);
    }

    public function test_autosave_essay(): void
    {
        $siswa = User::create([
            'name' => 'Siswa', 'email' => 's@t.test',
            'password' => bcrypt('x'), 'role' => 'siswa',
        ]);
        $mapel = MataPelajaran::create(['nama' => 'MTK', 'kode' => 'MTK']);
        $test = Test::create([
            'mata_pelajaran_id' => $mapel->id, 'judul' => 'Essay',
            'durasi' => 30, 'kkm' => 70, 'status' => 'published',
        ]);
        $q = Question::create([
            'mata_pelajaran_id' => $mapel->id, 'tipe' => 'essay',
            'pertanyaan' => 'Jelaskan.', 'bobot' => 5, 'tingkat_kesulitan' => 'sedang',
        ]);
        $test->questions()->attach($q->id, ['urutan' => 1]);
        $attempt = app(ExamSessionService::class)->startOrResume($test, $siswa->id);

        Livewire::actingAs($siswa)
            ->test(ExamRoom::class, ['attempt' => $attempt])
            ->set('essayDraft', 'Luas = panjang kali lebar.');

        $this->assertDatabaseHas('user_answers', [
            'test_attempt_id' => $attempt->id,
            'question_id' => $q->id,
            'jawaban_essay' => 'Luas = panjang kali lebar.',
        ]);
    }

    public function test_auto_submit_setelah_pelanggaran_capai_ambang(): void
    {
        [$siswa, $attempt] = $this->buatUjianTersnapshot();
        $attempt->test->update(['max_pelanggaran' => 2]);

        $component = Livewire::actingAs($siswa)->test(ExamRoom::class, ['attempt' => $attempt]);

        $component->call('recordViolation'); // 1 -> belum
        $attempt->refresh();
        $this->assertSame(AttemptStatus::SedangDikerjakan, $attempt->status);

        $component->call('recordViolation') // 2 -> auto-submit
            ->assertRedirect(route('exam.result', $attempt->id));

        $attempt->refresh();
        $this->assertSame(AttemptStatus::Selesai, $attempt->status);
        $this->assertSame(2, $attempt->pelanggaran);
    }
}
