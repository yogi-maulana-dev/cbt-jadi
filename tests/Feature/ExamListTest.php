<?php

namespace Tests\Feature;

use App\Livewire\Exam\ExamList;
use App\Models\Choice;
use App\Models\MataPelajaran;
use App\Models\Question;
use App\Models\Test;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ExamListTest extends TestCase
{
    use RefreshDatabase;

    public function test_render_dan_start_redirect(): void
    {
        $siswa = User::create([
            'name' => 'Siswa', 'email' => 's@t.test',
            'password' => bcrypt('x'), 'role' => 'siswa',
        ]);
        $mapel = MataPelajaran::create(['nama' => 'MTK', 'kode' => 'MTK']);
        $test = Test::create([
            'mata_pelajaran_id' => $mapel->id, 'judul' => 'UH Realtime',
            'durasi' => 30, 'kkm' => 70, 'status' => 'published',
        ]);
        $q = Question::create([
            'mata_pelajaran_id' => $mapel->id, 'tipe' => 'pilihan_ganda',
            'pertanyaan' => 'Soal', 'bobot' => 1, 'tingkat_kesulitan' => 'sedang',
        ]);
        Choice::create(['question_id' => $q->id, 'label' => 'A', 'teks' => 'a', 'urutan' => 1, 'is_correct' => true]);
        $test->questions()->attach($q->id, ['urutan' => 1]);

        Livewire::actingAs($siswa)
            ->test(ExamList::class)
            ->assertSee('UH Realtime')
            ->call('start', $test->id)
            ->assertRedirect();
    }

    public function test_token_salah_ditolak(): void
    {
        $siswa = User::create([
            'name' => 'Siswa', 'email' => 's2@t.test',
            'password' => bcrypt('x'), 'role' => 'siswa',
        ]);
        $mapel = MataPelajaran::create(['nama' => 'MTK', 'kode' => 'MTK']);
        $test = Test::create([
            'mata_pelajaran_id' => $mapel->id, 'judul' => 'UH Token',
            'durasi' => 30, 'kkm' => 70, 'status' => 'published', 'token' => 'RAHASIA',
        ]);
        $q = Question::create([
            'mata_pelajaran_id' => $mapel->id, 'tipe' => 'pilihan_ganda',
            'pertanyaan' => 'Soal', 'bobot' => 1, 'tingkat_kesulitan' => 'sedang',
        ]);
        Choice::create(['question_id' => $q->id, 'label' => 'A', 'teks' => 'a', 'urutan' => 1, 'is_correct' => true]);
        $test->questions()->attach($q->id, ['urutan' => 1]);

        Livewire::actingAs($siswa)
            ->test(ExamList::class)
            ->set("tokens.{$test->id}", 'SALAH')
            ->call('start', $test->id)
            ->assertHasErrors("start.{$test->id}")
            ->assertNoRedirect();
    }
}
