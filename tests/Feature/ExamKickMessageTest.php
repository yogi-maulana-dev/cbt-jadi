<?php

namespace Tests\Feature;

use App\Filament\Pages\PengaturanUjian;
use App\Livewire\Exam\ExamRoom;
use App\Models\Choice;
use App\Models\MataPelajaran;
use App\Models\Question;
use App\Models\Setting;
use App\Models\Test;
use App\Models\User;
use App\Services\ExamSessionService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\TestCase;

class ExamKickMessageTest extends TestCase
{
    use RefreshDatabase;

    private function buatUjianDanAttempt(): array
    {
        $mapel = MataPelajaran::create(['nama' => 'Matematika', 'kode' => 'MTK']);
        $q = Question::create([
            'mata_pelajaran_id' => $mapel->id, 'tipe' => 'pilihan_ganda',
            'pertanyaan' => 'Soal', 'bobot' => 1, 'tingkat_kesulitan' => 'sedang',
        ]);
        Choice::create(['question_id' => $q->id, 'label' => 'A', 'teks' => '1', 'urutan' => 1, 'is_correct' => true]);
        $test = Test::create([
            'mata_pelajaran_id' => $mapel->id, 'judul' => 'Ujian', 'durasi' => 60, 'status' => 'published',
        ]);
        $test->questions()->attach($q->id, ['urutan' => 1, 'bobot' => 1, 'cadangan' => false]);
        $siswa = User::factory()->create(['role' => 'siswa']);
        $attempt = app(ExamSessionService::class)->startOrResume($test, $siswa->id);

        return [$siswa, $test, $attempt];
    }

    public function test_setting_default_dan_bisa_dikustom(): void
    {
        $this->assertSame(Setting::DEFAULT_KICK_MESSAGE, Setting::kickMessage());

        Setting::set('exam_kick_message', 'Pesan khusus admin');
        $this->assertSame('Pesan khusus admin', Setting::kickMessage());
    }

    public function test_admin_bisa_menyimpan_pesan_lewat_halaman_pengaturan(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $admin = User::factory()->create(['role' => 'admin']);

        Livewire::actingAs($admin)
            ->test(PengaturanUjian::class)
            ->fillForm(['exam_kick_title' => 'Judul Baru', 'exam_kick_message' => 'Isi pesan baru'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Judul Baru', Setting::kickTitle());
        $this->assertSame('Isi pesan baru', Setting::kickMessage());
    }

    public function test_examroom_mengarahkan_ke_halaman_pemberitahuan_saat_direset(): void
    {
        [$siswa, , $attempt] = $this->buatUjianDanAttempt();

        $page = Livewire::actingAs($siswa)->test(ExamRoom::class, ['attempt' => $attempt]);

        // Pengawas mereset (hapus attempt) lalu heartbeat berikutnya menendang siswa.
        $attempt->delete();

        $page->call('heartbeat')->assertRedirect(route('exam.kicked'));
    }

    public function test_halaman_pemberitahuan_menampilkan_pesan_custom(): void
    {
        Setting::set('exam_kick_title', 'Judul Custom');
        Setting::set('exam_kick_message', 'Pesan maintenance custom');

        $siswa = User::factory()->create(['role' => 'siswa']);

        $this->actingAs($siswa)->get(route('exam.kicked'))
            ->assertOk()
            ->assertSee('Judul Custom')
            ->assertSee('Pesan maintenance custom');
    }

    public function test_api_410_memakai_pesan_custom(): void
    {
        Setting::set('exam_kick_message', 'Pesan API custom');

        [$siswa, $test] = $this->buatUjianDanAttempt();
        Sanctum::actingAs($siswa);
        $attemptId = $this->postJson("/api/exams/{$test->id}/start", [])->json('attempt_id');

        \App\Models\TestAttempt::findOrFail($attemptId)->delete();

        $this->getJson("/api/attempts/{$attemptId}/heartbeat")
            ->assertStatus(410)
            ->assertJson(['reset' => true, 'message' => 'Pesan API custom']);
    }
}
