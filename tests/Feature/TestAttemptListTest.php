<?php

namespace Tests\Feature;

use App\Filament\Resources\TestAttemptResource\Pages\ListTestAttempts;
use App\Filament\Resources\TestAttemptResource\Pages\LiveHasil;
use App\Models\Choice;
use App\Models\MataPelajaran;
use App\Models\Question;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;
use App\Models\UserAnswer;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TestAttemptListTest extends TestCase
{
    use RefreshDatabase;

    private function buatAttempt(): array
    {
        $mapel = MataPelajaran::create(['nama' => 'Matematika', 'kode' => 'MTK']);
        $test = Test::create([
            'mata_pelajaran_id' => $mapel->id, 'judul' => 'UH 1', 'durasi' => 60, 'status' => 'published',
        ]);
        $siswa = User::factory()->create(['role' => 'siswa']);
        $attempt = TestAttempt::create([
            'test_id' => $test->id, 'user_id' => $siswa->id,
            'waktu_mulai' => now(), 'deadline' => now()->addHour(), 'status' => 'sedang_dikerjakan',
        ]);

        return [$test, $attempt];
    }

    public function test_hasil_kosong_sebelum_pilih_ujian_lalu_terisi_setelah_pilih(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $admin = User::factory()->create(['role' => 'admin']);
        [$test, $attempt] = $this->buatAttempt();

        // Belum pilih ujian -> hasil tidak tampil.
        Livewire::actingAs($admin)
            ->test(ListTestAttempts::class)
            ->assertCanNotSeeTableRecords([$attempt]);

        // Setelah pilih ujian -> hasil peserta tampil.
        Livewire::actingAs($admin)
            ->test(ListTestAttempts::class, ['test' => (string) $test->id])
            ->assertCanSeeTableRecords([$attempt]);
    }

    public function test_halaman_live_menampilkan_peserta_yang_sedang_mengerjakan(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $admin = User::factory()->create(['role' => 'admin']);
        [$test, $attempt] = $this->buatAttempt();

        Livewire::actingAs($admin)
            ->test(LiveHasil::class, ['test' => (string) $test->id])
            ->assertSuccessful()
            ->assertSee($attempt->user->name)
            ->assertSee('sedang mengerjakan');
    }

    public function test_live_menghitung_benar_dan_salah_dari_pilihan_ganda(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $admin = User::factory()->create(['role' => 'admin']);
        $mapel = MataPelajaran::create(['nama' => 'Matematika', 'kode' => 'MTK']);
        $test = Test::create(['mata_pelajaran_id' => $mapel->id, 'judul' => 'UH', 'durasi' => 60, 'status' => 'published']);
        $siswa = User::factory()->create(['role' => 'siswa']);
        $attempt = TestAttempt::create([
            'test_id' => $test->id, 'user_id' => $siswa->id,
            'waktu_mulai' => now(), 'deadline' => now()->addHour(), 'status' => 'sedang_dikerjakan',
        ]);

        // Soal 1: jawab BENAR.
        $q1 = Question::create(['mata_pelajaran_id' => $mapel->id, 'tipe' => 'pilihan_ganda', 'pertanyaan' => 'S1', 'bobot' => 1, 'tingkat_kesulitan' => 'sedang']);
        $b1 = Choice::create(['question_id' => $q1->id, 'label' => 'A', 'teks' => 'benar', 'urutan' => 1, 'is_correct' => true]);
        Choice::create(['question_id' => $q1->id, 'label' => 'B', 'teks' => 'salah', 'urutan' => 2, 'is_correct' => false]);
        UserAnswer::create(['test_attempt_id' => $attempt->id, 'question_id' => $q1->id, 'choice_id' => $b1->id]);

        // Soal 2: jawab SALAH.
        $q2 = Question::create(['mata_pelajaran_id' => $mapel->id, 'tipe' => 'pilihan_ganda', 'pertanyaan' => 'S2', 'bobot' => 1, 'tingkat_kesulitan' => 'sedang']);
        Choice::create(['question_id' => $q2->id, 'label' => 'A', 'teks' => 'benar', 'urutan' => 1, 'is_correct' => true]);
        $s2 = Choice::create(['question_id' => $q2->id, 'label' => 'B', 'teks' => 'salah', 'urutan' => 2, 'is_correct' => false]);
        UserAnswer::create(['test_attempt_id' => $attempt->id, 'question_id' => $q2->id, 'choice_id' => $s2->id]);

        // Soal 3: ESSAY (tidak boleh terhitung benar/salah, masuk hitungan essay).
        $q3 = Question::create(['mata_pelajaran_id' => $mapel->id, 'tipe' => 'essay', 'pertanyaan' => 'Jelaskan', 'bobot' => 5, 'tingkat_kesulitan' => 'sedang']);
        UserAnswer::create(['test_attempt_id' => $attempt->id, 'question_id' => $q3->id, 'jawaban_essay' => 'jawaban panjang']);

        $row = Livewire::actingAs($admin)
            ->test(LiveHasil::class, ['test' => (string) $test->id])
            ->instance()
            ->getPeserta()
            ->firstWhere('id', $attempt->id);

        $this->assertSame(1, (int) $row->benar_count);   // essay tidak menambah benar
        $this->assertSame(1, (int) $row->salah_count);   // essay tidak menambah salah
        $this->assertSame(1, (int) $row->essay_count);   // essay dihitung terpisah
    }
}
