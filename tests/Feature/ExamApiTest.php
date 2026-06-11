<?php

namespace Tests\Feature;

use App\Models\Choice;
use App\Models\MataPelajaran;
use App\Models\Question;
use App\Models\Test;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExamApiTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:User,1:Test,2:array<int,int>} */
    private function buildExam(): array
    {
        $siswa = User::create([
            'name' => 'Siswa', 'email' => 'siswa@api.test',
            'password' => bcrypt('password'), 'role' => 'siswa',
        ]);
        $mapel = MataPelajaran::create(['nama' => 'MTK', 'kode' => 'MTK']);
        $test = Test::create([
            'mata_pelajaran_id' => $mapel->id, 'judul' => 'UH API',
            'durasi' => 30, 'kkm' => 70, 'status' => 'published',
        ]);

        $kunci = [];
        foreach (range(1, 2) as $n) {
            $q = Question::create([
                'mata_pelajaran_id' => $mapel->id, 'tipe' => 'pilihan_ganda',
                'pertanyaan' => "Soal $n", 'bobot' => 1, 'tingkat_kesulitan' => 'sedang',
            ]);
            $benar = Choice::create(['question_id' => $q->id, 'label' => 'A', 'teks' => 'benar', 'urutan' => 1, 'is_correct' => true]);
            Choice::create(['question_id' => $q->id, 'label' => 'B', 'teks' => 'salah', 'urutan' => 2, 'is_correct' => false]);
            $test->questions()->attach($q->id, ['urutan' => $n]);
            $kunci[$q->id] = $benar->id;
        }

        return [$siswa, $test, $kunci];
    }

    /** @return array{captcha_id:string,captcha_answer:int} */
    private function captchaPayload(): array
    {
        $c = $this->getJson('/api/captcha')->json();
        [$a, , $b] = explode(' ', $c['question']);

        return ['captcha_id' => $c['captcha_id'], 'captcha_answer' => (int) $a + (int) $b];
    }

    public function test_login_mengembalikan_token(): void
    {
        $this->buildExam();

        $res = $this->postJson('/api/login', array_merge([
            'email' => 'siswa@api.test',
            'password' => 'password',
            'device_name' => 'android',
        ], $this->captchaPayload()));

        $res->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'role']]);
    }

    public function test_login_salah_ditolak(): void
    {
        $this->buildExam();
        $this->postJson('/api/login', array_merge(
            ['email' => 'siswa@api.test', 'password' => 'salah'],
            $this->captchaPayload()
        ))->assertStatus(422);
    }

    public function test_butuh_autentikasi(): void
    {
        $this->getJson('/api/exams')->assertStatus(401);
    }

    public function test_snapshot_tidak_membocorkan_kunci_jawaban(): void
    {
        [$siswa, $test] = $this->buildExam();
        Sanctum::actingAs($siswa);

        $res = $this->postJson("/api/exams/{$test->id}/start", []);

        $res->assertOk()
            ->assertJsonStructure([
                'attempt_id', 'judul', 'deadline',
                'questions' => [['id', 'tipe', 'pertanyaan', 'choices', 'ragu']],
            ]);

        // KUNCI JAWABAN tidak boleh ikut terkirim ke siswa.
        $this->assertStringNotContainsString('is_correct', $res->getContent());
    }

    public function test_jawab_lalu_selesai_dinilai_otomatis(): void
    {
        [$siswa, $test, $kunci] = $this->buildExam();
        Sanctum::actingAs($siswa);

        $start = $this->postJson("/api/exams/{$test->id}/start", [])->json();
        $attemptId = $start['attempt_id'];

        foreach ($kunci as $questionId => $choiceId) {
            $this->postJson("/api/attempts/{$attemptId}/answer", [
                'question_id' => $questionId,
                'choice_id' => $choiceId,
            ])->assertOk();
        }

        $this->postJson("/api/attempts/{$attemptId}/finish")
            ->assertOk()
            ->assertJson(['skor' => 100.0, 'jumlah_benar' => 2, 'lulus' => true]);
    }

    public function test_violation_auto_submit_dan_blokir(): void
    {
        [$siswa, $test] = $this->buildExam();
        $test->update(['max_pelanggaran' => 2]);
        Sanctum::actingAs($siswa);

        $attemptId = $this->postJson("/api/exams/{$test->id}/start", [])->json('attempt_id');

        $this->postJson("/api/attempts/{$attemptId}/violation")
            ->assertOk()
            ->assertJson(['pelanggaran' => 1, 'finished' => false, 'blocked' => false]);

        $this->postJson("/api/attempts/{$attemptId}/violation")
            ->assertOk()
            ->assertJson(['pelanggaran' => 2, 'finished' => true, 'blocked' => true]);

        $this->assertTrue($siswa->fresh()->isBlocked());
        $this->assertSame($test->id, (int) $siswa->fresh()->diblokir_test_id);
    }

    public function test_tidak_bisa_akses_attempt_siswa_lain(): void
    {
        [$siswa, $test, $kunci] = $this->buildExam();
        Sanctum::actingAs($siswa);
        $attemptId = $this->postJson("/api/exams/{$test->id}/start", [])->json('attempt_id');

        $lain = User::create([
            'name' => 'Lain', 'email' => 'lain@api.test',
            'password' => bcrypt('password'), 'role' => 'siswa',
        ]);
        Sanctum::actingAs($lain);

        $this->getJson("/api/attempts/{$attemptId}/result")->assertStatus(403);
    }
}
