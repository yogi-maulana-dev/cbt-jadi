<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Cetak Kartu Ujian siswa ke dokumen Word (.docx).
 */
class KartuUjianExport
{
    /**
     * Tentukan daftar siswa untuk dicetak: per-id (?ids=), per-kelas (?kelas=), atau semua (?all=1).
     *
     * @return Collection<int, User>
     */
    public static function resolveSiswa(Request $request): Collection
    {
        $q = User::where('role', 'siswa');

        $ids = array_filter(explode(',', (string) $request->query('ids', '')));
        $kelas = $request->query('kelas');

        if (! empty($ids)) {
            $q->whereIn('id', $ids);
        } elseif (filled($kelas)) {
            $q->where('kelas', $kelas);
        } elseif (! $request->boolean('all')) {
            // Tidak ada kriteria & bukan "semua" -> kosong (hindari cetak tak sengaja).
            $q->whereRaw('1 = 0');
        }

        return $q->orderBy('kelas')->orderBy('name')->get();
    }

    private function imagePath(?string $rel): ?string
    {
        if (! $rel) {
            return null;
        }
        $path = Storage::disk('public')->path($rel);

        return is_file($path) ? $path : null;
    }

    public function word(Collection $siswa): StreamedResponse
    {
        $namaSekolah = Setting::namaSekolah();
        $tahun = Setting::tahunPelajaran();
        $judul = Setting::judulKartu();
        $kepsek = Setting::kepalaSekolah();
        $logo = $this->imagePath(Setting::logoSekolah());
        $logoBawah = $this->imagePath(Setting::logoBawah());
        $ttd = $this->imagePath(Setting::ttdGambar());

        $word = new PhpWord();
        $section = $word->addSection(['marginTop' => 600, 'marginBottom' => 600, 'marginLeft' => 700, 'marginRight' => 700]);

        $cardStyle = ['borderSize' => 10, 'borderColor' => '000000', 'cellMargin' => 120, 'width' => 100 * 50, 'unit' => 'pct'];

        foreach ($siswa as $s) {
            $tbl = $section->addTable($cardStyle);
            $tbl->addRow();
            $cell = $tbl->addCell(9000);

            // Header
            if ($logo) {
                $cell->addImage($logo, ['width' => 42, 'height' => 42, 'alignment' => 'center']);
            }
            $cell->addText(htmlspecialchars($namaSekolah), ['bold' => true, 'size' => 14], ['alignment' => 'center', 'spaceAfter' => 0]);
            $cell->addText('UJIAN SEKOLAH TP. '.htmlspecialchars($tahun), ['size' => 9], ['alignment' => 'center', 'spaceAfter' => 60]);

            // Judul
            $cell->addText(htmlspecialchars($judul), ['bold' => true, 'size' => 12], ['spaceBefore' => 80, 'spaceAfter' => 80]);

            // Field
            foreach ([
                'NO UJIAN' => $s->no_ujian,
                'NAMA' => $s->name,
                'KELAS' => $s->kelas,
                'PROGRAM STUDI' => $s->program_studi,
            ] as $label => $val) {
                $cell->addText(str_pad($label, 14).': '.htmlspecialchars((string) ($val ?? '-')), ['size' => 11], ['spaceAfter' => 20]);
            }

            // Tanda tangan (kanan)
            $cell->addText('Kepala Sekolah', ['size' => 11], ['alignment' => 'right', 'spaceBefore' => 120]);
            if ($ttd) {
                $cell->addImage($ttd, ['width' => 90, 'height' => 50, 'alignment' => 'right']);
            } else {
                $cell->addTextBreak(2);
            }
            $cell->addText(htmlspecialchars($kepsek ?: '________________'), ['bold' => true, 'size' => 11], ['alignment' => 'right']);

            if ($logoBawah) {
                $cell->addImage($logoBawah, ['width' => 60, 'height' => 26, 'alignment' => 'left']);
            }

            $section->addTextBreak(1);
        }

        $writer = IOFactory::createWriter($word, 'Word2007');
        $filename = 'kartu-ujian-'.date('Ymd-His').'.docx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
    }
}
