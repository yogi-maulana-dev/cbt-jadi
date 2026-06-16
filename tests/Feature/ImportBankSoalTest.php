<?php

namespace Tests\Feature;

use App\Models\Choice;
use App\Models\MataPelajaran;
use App\Models\Question;
use App\Services\BankSoalExport;
use App\Services\BankSoalImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class ImportBankSoalTest extends TestCase
{
    use RefreshDatabase;

    private function buatFileExcel(array $rows): string
    {
        $ss = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle('Soal');
        $sheet->fromArray(BankSoalImport::HEADERS, null, 'A1');
        $sheet->fromArray($rows, null, 'A2');

        $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
        (new Xlsx($ss))->save($path);

        return $path;
    }

    public function test_template_bisa_diunduh(): void
    {
        $this->assertInstanceOf(StreamedResponse::class, app(BankSoalImport::class)->template());
    }

    public function test_import_membuat_soal_dan_melaporkan_slip(): void
    {
        MataPelajaran::create(['nama' => 'Matematika', 'kode' => 'MTK']);

        $path = $this->buatFileExcel([
            ['MTK', 'pilihan_ganda', 'sedang', 1, '2 + 2 = ...', '', '', '3', '4', '', '', '', 'B', 'dua'],
            ['MTK', 'essay', 'sedang', 5, 'Jelaskan luas persegi', '', 'https://youtu.be/x', '', '', '', '', '', '', ''],
            ['XXX', 'pilihan_ganda', 'sedang', 1, 'Mapel tak dikenal', '', '', 'a', 'b', '', '', '', 'A', ''],
            ['MTK', 'pilihan_ganda', 'sedang', 1, 'Tanpa kunci', '', '', 'a', 'b', '', '', '', '', ''],
            ['MTK', 'pilihan_ganda', 'sedang', 1, 'Pakai gambar', 'nope.png', '', 'a', 'b', '', '', '', 'A', ''],
        ]);

        $report = app(BankSoalImport::class)->import($path, null, null);
        @unlink($path);

        // 4 dibuat (baris mapel XXX dilewati).
        $this->assertSame(4, $report['imported']);
        $this->assertSame(1, $report['with_video']);
        $this->assertSame(0, $report['with_image']); // nope.png tak ada

        // Peringatan: mapel tak dikenal, tanpa kunci, gambar hilang.
        $this->assertGreaterThanOrEqual(3, count($report['warnings']));

        // Kunci jawaban benar terpasang.
        $q = Question::where('pertanyaan', '2 + 2 = ...')->first();
        $this->assertTrue($q->choices()->where('teks', '4')->first()->is_correct);

        // Video tersimpan.
        $this->assertSame('https://youtu.be/x', Question::where('pertanyaan', 'Jelaskan luas persegi')->value('video_url'));

        // Semua soal hasil import ditandai satu batch yang sama (untuk halaman Lengkapi Media).
        $this->assertNotNull($report['batch']);
        $this->assertSame(4, Question::where('import_batch', $report['batch'])->count());
    }

    public function test_round_trip_export_lalu_import_dengan_gambar_tertanam(): void
    {
        Storage::fake('public');
        // PNG 1x1 valid.
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        Storage::disk('public')->put('soal/asli.png', $png);

        $mapel = MataPelajaran::create(['nama' => 'Matematika', 'kode' => 'MTK']);
        $q = Question::create([
            'mata_pelajaran_id' => $mapel->id, 'tipe' => 'pilihan_ganda',
            'pertanyaan' => 'Soal bergambar', 'bobot' => 1, 'tingkat_kesulitan' => 'sedang',
            'gambar' => 'soal/asli.png',
        ]);
        Choice::create(['question_id' => $q->id, 'label' => 'A', 'teks' => 'Benar', 'urutan' => 1, 'is_correct' => true]);
        Choice::create(['question_id' => $q->id, 'label' => 'B', 'teks' => 'Salah', 'urutan' => 2, 'is_correct' => false]);

        // Export -> simpan ke file.
        $res = app(BankSoalExport::class)->excel(Question::with(['choices', 'mataPelajaran.jurusan'])->get());
        ob_start();
        $res->sendContent();
        $bytes = (string) ob_get_clean();
        $tmp = tempnam(sys_get_temp_dir(), 'rt').'.xlsx';
        file_put_contents($tmp, $bytes);

        // Hapus data asli.
        Choice::query()->delete();
        Question::query()->delete();

        // Import ulang dari hasil export.
        $report = app(BankSoalImport::class)->import($tmp, null, null);
        @unlink($tmp);

        $this->assertSame(1, $report['imported']);
        $this->assertSame(1, $report['with_image']); // gambar tertanam terbaca

        $q2 = Question::where('pertanyaan', 'Soal bergambar')->first();
        $this->assertNotNull($q2);
        $this->assertNotNull($q2->gambar);
        $this->assertTrue(Storage::disk('public')->exists($q2->gambar));
        $this->assertTrue($q2->choices()->where('teks', 'Benar')->first()->is_correct);
    }

    public function test_import_audio_dari_nama_file_dan_pending_bila_hilang(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('soal-audio/listening.mp3', 'dummy-audio');
        MataPelajaran::create(['nama' => 'Bahasa Inggris', 'kode' => 'BIG']);

        // Kolom ke-15 = Suara (nama file).
        $path = $this->buatFileExcel([
            ['BIG', 'pilihan_ganda', 'sedang', 1, 'Dengarkan audio', '', '', 'a', 'b', '', '', '', 'A', '', 'listening.mp3'],
            ['BIG', 'pilihan_ganda', 'sedang', 1, 'Audio hilang', '', '', 'a', 'b', '', '', '', 'A', '', 'tidak-ada.mp3'],
        ]);

        $report = app(BankSoalImport::class)->import($path, null, null);
        @unlink($path);

        $this->assertSame(1, $report['with_audio']);

        $q1 = Question::where('pertanyaan', 'Dengarkan audio')->first();
        $this->assertSame('soal-audio/listening.mp3', $q1->suara);
        $this->assertFalse($q1->media_pending);

        $q2 = Question::where('pertanyaan', 'Audio hilang')->first();
        $this->assertNull($q2->suara);
        $this->assertTrue($q2->media_pending); // dideklarasikan tapi file tak ada
    }

    public function test_operator_hanya_bisa_impor_mapel_yang_diampu(): void
    {
        $mtk = MataPelajaran::create(['nama' => 'Matematika', 'kode' => 'MTK']);
        MataPelajaran::create(['nama' => 'IPA', 'kode' => 'IPA']);

        $path = $this->buatFileExcel([
            ['MTK', 'pilihan_ganda', 'sedang', 1, 'Soal MTK', '', '', 'a', 'b', '', '', '', 'A', ''],
            ['IPA', 'pilihan_ganda', 'sedang', 1, 'Soal IPA', '', '', 'a', 'b', '', '', '', 'A', ''],
        ]);

        // Guru hanya ampu MTK -> soal IPA ditolak.
        $report = app(BankSoalImport::class)->import($path, null, [$mtk->id]);
        @unlink($path);

        $this->assertSame(1, $report['imported']);
        $this->assertNotEmpty($report['warnings']);
        $this->assertDatabaseMissing('questions', ['pertanyaan' => 'Soal IPA']);
    }

    public function test_url_video_tidak_valid_memicu_peringatan_dan_soal_tanpa_video(): void
    {
        MataPelajaran::create(['nama' => 'Bahasa Indonesia', 'kode' => 'BIN']);

        $path = $this->buatFileExcel([
            ['BIN', 'pilihan_ganda', 'sedang', 1, 'Video ngawur', '', 'tonton di youtube ya', 'a', 'b', '', '', '', 'A', ''],
            ['BIN', 'pilihan_ganda', 'sedang', 1, 'Video valid', '', 'https://youtu.be/abc', 'a', 'b', '', '', '', 'A', ''],
        ]);

        $report = app(BankSoalImport::class)->import($path, null, null);
        @unlink($path);

        $this->assertSame(2, $report['imported']);
        $this->assertSame(1, $report['with_video']); // hanya yang valid dihitung

        // Soal video ngawur tetap dibuat tapi tanpa video, dan ada peringatan menyebut mapel.
        $this->assertNull(Question::where('pertanyaan', 'Video ngawur')->value('video_url'));
        $this->assertSame('https://youtu.be/abc', Question::where('pertanyaan', 'Video valid')->value('video_url'));
        $this->assertTrue(collect($report['warnings'])->contains(
            fn ($w) => str_contains($w, 'URL video') && str_contains($w, 'BIN - Bahasa Indonesia')
        ));
    }

    public function test_soal_yang_deklarasi_media_tapi_gagal_ditandai_media_pending(): void
    {
        MataPelajaran::create(['nama' => 'Matematika', 'kode' => 'MTK']);

        $path = $this->buatFileExcel([
            // Teks polos -> tidak pending.
            ['MTK', 'pilihan_ganda', 'sedang', 1, 'Soal teks polos', '', '', 'a', 'b', '', '', '', 'A', ''],
            // Deklarasi gambar tapi file tak ada -> pending.
            ['MTK', 'pilihan_ganda', 'sedang', 1, 'Soal butuh gambar', 'tidak-ada.png', '', 'a', 'b', '', '', '', 'A', ''],
            // Deklarasi video tapi URL ngawur -> pending.
            ['MTK', 'pilihan_ganda', 'sedang', 1, 'Soal butuh video', '', 'bukan-url', 'a', 'b', '', '', '', 'A', ''],
        ]);

        app(BankSoalImport::class)->import($path, null, null);
        @unlink($path);

        $this->assertFalse(Question::where('pertanyaan', 'Soal teks polos')->value('media_pending'));
        $this->assertTrue((bool) Question::where('pertanyaan', 'Soal butuh gambar')->value('media_pending'));
        $this->assertTrue((bool) Question::where('pertanyaan', 'Soal butuh video')->value('media_pending'));
    }

    public function test_soal_duplikat_terdeteksi_dan_dilewati(): void
    {
        $mapel = MataPelajaran::create(['nama' => 'Matematika', 'kode' => 'MTK']);

        // Soal yang sudah ada di DB.
        $q = Question::create([
            'mata_pelajaran_id' => $mapel->id, 'tipe' => 'pilihan_ganda',
            'pertanyaan' => '2 + 2 = ...', 'bobot' => 1, 'tingkat_kesulitan' => 'sedang',
        ]);
        Choice::create(['question_id' => $q->id, 'label' => 'A', 'teks' => '3', 'urutan' => 1, 'is_correct' => false]);
        Choice::create(['question_id' => $q->id, 'label' => 'B', 'teks' => '4', 'urutan' => 2, 'is_correct' => true]);

        $path = $this->buatFileExcel([
            // Duplikat dari DB: teks soal + opsi sama (beda urutan & spasi/kapital -> tetap dianggap sama).
            ['MTK', 'pilihan_ganda', 'sedang', 1, '2 + 2 =  ...', '', '', '4', '3', '', '', '', 'A', ''],
            // Soal baru (unik).
            ['MTK', 'pilihan_ganda', 'sedang', 1, '5 + 5 = ...', '', '', '9', '10', '', '', '', 'B', ''],
            // Duplikat antar-baris di file ini (sama dengan baris sebelumnya).
            ['MTK', 'pilihan_ganda', 'sedang', 1, '5 + 5 = ...', '', '', '10', '9', '', '', '', 'A', ''],
        ]);

        $report = app(BankSoalImport::class)->import($path, null, null);
        @unlink($path);

        // Hanya 1 soal baru yang dibuat; 2 duplikat dilewati.
        $this->assertSame(1, $report['imported']);
        $this->assertSame(2, $report['duplicates']);
        $this->assertSame(2, Question::where('mata_pelajaran_id', $mapel->id)->count()); // 1 lama + 1 baru
        $this->assertTrue(collect($report['warnings'])->contains(fn ($w) => str_contains($w, 'duplikat')));
    }
}
