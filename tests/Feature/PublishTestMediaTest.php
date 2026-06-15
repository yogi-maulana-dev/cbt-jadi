<?php

namespace Tests\Feature;

use App\Filament\Resources\TestResource\Pages\EditTest;
use App\Models\MataPelajaran;
use App\Models\Question;
use App\Models\Test;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PublishTestMediaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function buatUjianDenganSoal(bool $pending): array
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $mapel = MataPelajaran::create(['nama' => 'Matematika', 'kode' => 'MTK']);

        $q = Question::create([
            'mata_pelajaran_id' => $mapel->id, 'created_by' => $admin->id, 'media_pending' => $pending,
            'tipe' => 'pilihan_ganda', 'pertanyaan' => 'Soal ujian', 'bobot' => 1, 'tingkat_kesulitan' => 'sedang',
        ]);

        $test = Test::create([
            'mata_pelajaran_id' => $mapel->id, 'created_by' => $admin->id,
            'judul' => 'Ujian Uji', 'durasi' => 60, 'status' => 'draft',
        ]);
        $test->questions()->attach($q->id, ['urutan' => 1, 'bobot' => 1]);

        return [$admin, $test];
    }

    public function test_tidak_bisa_publish_bila_ada_soal_media_pending(): void
    {
        [$admin, $test] = $this->buatUjianDenganSoal(pending: true);

        Livewire::actingAs($admin)
            ->test(EditTest::class, ['record' => $test->id])
            ->fillForm(['status' => 'published'])
            ->call('save');

        // Publish dibatalkan -> tetap draft.
        $this->assertSame('draft', $test->fresh()->status->value);
    }

    public function test_bisa_publish_bila_semua_soal_lengkap(): void
    {
        [$admin, $test] = $this->buatUjianDenganSoal(pending: false);

        Livewire::actingAs($admin)
            ->test(EditTest::class, ['record' => $test->id])
            ->fillForm(['status' => 'published'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('published', $test->fresh()->status->value);
    }
}
