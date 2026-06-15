<?php

namespace Tests\Feature;

use App\Filament\Resources\QuestionResource;
use App\Filament\Resources\QuestionResource\Pages\LengkapiMedia;
use App\Models\MataPelajaran;
use App\Models\Question;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class LengkapiMediaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_halaman_hanya_memuat_soal_dari_batch_lalu_menyimpan_media(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $mapel = MataPelajaran::create(['nama' => 'Matematika', 'kode' => 'MTK']);

        $q1 = Question::create([
            'mata_pelajaran_id' => $mapel->id, 'import_batch' => 'batch-A',
            'tipe' => 'pilihan_ganda', 'pertanyaan' => 'Soal satu', 'bobot' => 1, 'tingkat_kesulitan' => 'sedang',
        ]);
        $q2 = Question::create([
            'mata_pelajaran_id' => $mapel->id, 'import_batch' => 'batch-A',
            'tipe' => 'pilihan_ganda', 'pertanyaan' => 'Soal dua', 'bobot' => 1, 'tingkat_kesulitan' => 'sedang',
        ]);
        // Soal dari batch lain TIDAK boleh ikut muncul.
        Question::create([
            'mata_pelajaran_id' => $mapel->id, 'import_batch' => 'batch-LAIN',
            'tipe' => 'pilihan_ganda', 'pertanyaan' => 'Soal batch lain', 'bobot' => 1, 'tingkat_kesulitan' => 'sedang',
        ]);

        $page = Livewire::actingAs($admin)->test(LengkapiMedia::class, ['batch' => 'batch-A']);

        $soal = $page->get('data.soal');
        $this->assertCount(2, $soal); // hanya batch-A

        // Item Repeater berkunci uuid; ambil kunci pertama (urut id -> q1).
        $keys = array_keys($soal);
        $this->assertSame($q1->id, $soal[$keys[0]]['id']);

        $page->set("data.soal.{$keys[0]}.video_url", 'https://youtu.be/abc')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('https://youtu.be/abc', $q1->fresh()->video_url);
        $this->assertNull($q2->fresh()->video_url);
    }

    public function test_mengisi_media_tersimpan_otomatis_tanpa_tombol_simpan_dan_melepas_penanda(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $mapel = MataPelajaran::create(['nama' => 'Matematika', 'kode' => 'MTK']);

        $q = Question::create([
            'mata_pelajaran_id' => $mapel->id, 'import_batch' => 'batch-X', 'media_pending' => true,
            'tipe' => 'pilihan_ganda', 'pertanyaan' => 'Perlu media', 'bobot' => 1, 'tingkat_kesulitan' => 'sedang',
        ]);

        $page = Livewire::actingAs($admin)->test(LengkapiMedia::class, ['batch' => 'batch-X']);
        $keys = array_keys($page->get('data.soal'));

        // Hanya mengubah field (memicu auto-save), TANPA memanggil save().
        $page->set("data.soal.{$keys[0]}.video_url", 'https://youtu.be/zzz');

        $fresh = $q->fresh();
        $this->assertSame('https://youtu.be/zzz', $fresh->video_url); // tersimpan otomatis
        $this->assertFalse($fresh->media_pending);                    // penanda otomatis lepas
    }

    public function test_unggah_gambar_tersimpan_otomatis_ke_database(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);
        $mapel = MataPelajaran::create(['nama' => 'Matematika', 'kode' => 'MTK']);

        $q = Question::create([
            'mata_pelajaran_id' => $mapel->id, 'import_batch' => 'batch-IMG', 'media_pending' => true,
            'tipe' => 'pilihan_ganda', 'pertanyaan' => 'Perlu gambar', 'bobot' => 1, 'tingkat_kesulitan' => 'sedang',
        ]);

        $page = Livewire::actingAs($admin)->test(LengkapiMedia::class, ['batch' => 'batch-IMG']);
        $keys = array_keys($page->get('data.soal'));

        $page->set("data.soal.{$keys[0]}.gambar", UploadedFile::fake()->image('soal.png'));

        $fresh = $q->fresh();
        $this->assertNotNull($fresh->gambar, 'Path gambar harus tersimpan di DB');
        Storage::disk('public')->assertExists($fresh->gambar);
        $this->assertFalse($fresh->media_pending);
    }

    public function test_tombol_lengkapi_media_menunjuk_batch_terakhir_yang_belum_lengkap(): void
    {
        $mapel = MataPelajaran::create(['nama' => 'Matematika', 'kode' => 'MTK']);
        $base = ['mata_pelajaran_id' => $mapel->id, 'tipe' => 'pilihan_ganda', 'bobot' => 1, 'tingkat_kesulitan' => 'sedang'];

        // Batch lama: sudah lengkap.
        Question::create($base + ['import_batch' => 'lama', 'media_pending' => false, 'pertanyaan' => 'A']);
        // Batch baru: ada 1 soal pending + 1 sudah lengkap.
        Question::create($base + ['import_batch' => 'baru', 'media_pending' => true, 'pertanyaan' => 'B']);
        Question::create($base + ['import_batch' => 'baru', 'media_pending' => false, 'pertanyaan' => 'C']);

        $this->assertSame('baru', QuestionResource::pendingMediaBatch());
        $this->assertSame(1, QuestionResource::pendingMediaCount());

        // Total untuk badge menu menghitung SEMUA soal pending (lintas batch).
        $this->assertSame(1, QuestionResource::pendingMediaTotal());
        $this->assertSame('1', QuestionResource::getNavigationBadge());

        // Bila semua sudah lengkap -> tidak ada batch pending (tombol disembunyikan).
        Question::where('import_batch', 'baru')->update(['media_pending' => false]);
        $this->assertNull(QuestionResource::pendingMediaBatch());
        $this->assertSame(0, QuestionResource::pendingMediaCount());
        $this->assertNull(QuestionResource::getNavigationBadge());
    }
}
