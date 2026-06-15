<?php

namespace Tests\Feature;

use App\Models\Choice;
use App\Models\Jurusan;
use App\Models\MataPelajaran;
use App\Models\Question;
use App\Services\BankSoalExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class ExportBankSoalTest extends TestCase
{
    use RefreshDatabase;

    /** @return \Illuminate\Support\Collection<int, Question> */
    private function questions()
    {
        $jurusan = Jurusan::create(['nama' => 'RPL', 'kode' => 'RPL']);
        $mapel = MataPelajaran::create(['nama' => 'Pemrograman', 'kode' => 'PRG', 'jurusan_id' => $jurusan->id]);

        $q = Question::create([
            'mata_pelajaran_id' => $mapel->id, 'tipe' => 'pilihan_ganda',
            'pertanyaan' => 'Apa itu variabel?', 'bobot' => 1, 'tingkat_kesulitan' => 'sedang',
            'pembahasan' => 'Wadah data.',
        ]);
        Choice::create(['question_id' => $q->id, 'label' => 'A', 'teks' => 'Wadah data', 'urutan' => 1, 'is_correct' => true]);
        Choice::create(['question_id' => $q->id, 'label' => 'B', 'teks' => 'Fungsi', 'urutan' => 2, 'is_correct' => false]);

        return Question::with(['choices', 'mataPelajaran.jurusan'])->get();
    }

    private function body(StreamedResponse $res): string
    {
        ob_start();
        $res->sendContent();

        return (string) ob_get_clean();
    }

    public function test_export_excel_menghasilkan_xlsx_valid(): void
    {
        $res = app(BankSoalExport::class)->excel($this->questions());

        $this->assertInstanceOf(StreamedResponse::class, $res);
        $body = $this->body($res);
        $this->assertStringStartsWith('PK', $body);   // xlsx = arsip ZIP
        $this->assertGreaterThan(2000, strlen($body));
    }

    public function test_export_word_menghasilkan_docx_valid(): void
    {
        $res = app(BankSoalExport::class)->word($this->questions());

        $body = $this->body($res);
        $this->assertStringStartsWith('PK', $body);   // docx = arsip ZIP
        $this->assertGreaterThan(2000, strlen($body));
    }
}
