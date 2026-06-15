<?php

namespace Tests\Feature;

use App\Filament\Resources\QuestionResource\Pages\ListQuestions;
use App\Models\MataPelajaran;
use App\Models\Question;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class QuestionListSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_halaman_bank_soal_render(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $admin = User::factory()->create(['role' => 'admin']);

        Livewire::actingAs($admin)
            ->test(ListQuestions::class)
            ->assertSuccessful();
    }

    public function test_tabel_kosong_sebelum_pilih_mapel_dan_terisi_setelah_pilih(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $admin = User::factory()->create(['role' => 'admin']);
        $mapel = MataPelajaran::create(['nama' => 'Matematika', 'kode' => 'MTK']);
        $q = Question::create([
            'mata_pelajaran_id' => $mapel->id, 'tipe' => 'pilihan_ganda',
            'pertanyaan' => 'Soal mapel ini', 'bobot' => 1, 'tingkat_kesulitan' => 'sedang',
        ]);

        // Belum pilih mapel -> soal tidak tampil.
        Livewire::actingAs($admin)
            ->test(ListQuestions::class)
            ->assertCanNotSeeTableRecords([$q]);

        // Setelah pilih mapel -> soal tampil.
        Livewire::actingAs($admin)
            ->test(ListQuestions::class, ['mapel' => (string) $mapel->id])
            ->assertCanSeeTableRecords([$q]);
    }
}
