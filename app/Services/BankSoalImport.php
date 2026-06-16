<?php

namespace App\Services;

use App\Models\MataPelajaran;
use App\Models\Question;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Import Bank Soal dari Excel + template dengan petunjuk gambar/video.
 */
class BankSoalImport
{
    public const HEADERS = [
        'Kode Mapel', 'Tipe', 'Kesulitan', 'Bobot', 'Pertanyaan',
        'Gambar', 'Video (URL)',
        'Opsi A', 'Opsi B', 'Opsi C', 'Opsi D', 'Opsi E',
        'Jawaban Benar', 'Pembahasan',
        'Suara (nama file)',
    ];

    // ----------------------- TEMPLATE -----------------------

    public function template(): StreamedResponse
    {
        $ss = new Spreadsheet();

        $sheet = $ss->getActiveSheet();
        $sheet->setTitle('Soal');
        $sheet->fromArray(self::HEADERS, null, 'A1');
        $sheet->getStyle('A1:O1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4F46E5');
        $sheet->getStyle('A1:O1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');

        foreach (range('A', 'O') as $col) {
            $sheet->getColumnDimension($col)->setWidth(16);
        }
        $sheet->getColumnDimension('E')->setWidth(40);
        $sheet->getColumnDimension('N')->setWidth(30);

        // Contoh baris (boleh dihapus).
        $sheet->fromArray([
            ['MTK', 'pilihan_ganda', 'sedang', 1, 'Hasil dari 2 + 2 adalah ...', '', '', '3', '4', '5', '6', '', 'B', '2 + 2 = 4'],
            ['MTK', 'pilihan_ganda', 'sedang', 1, 'Lihat gambar, bangun apa ini?', 'contoh.png', '', 'Persegi', 'Lingkaran', 'Segitiga', 'Trapesium', '', 'A', ''],
            ['MTK', 'essay', 'sedang', 5, 'Jelaskan cara mencari luas persegi.', '', 'https://youtu.be/contoh', '', '', '', '', '', '', 'Luas = sisi x sisi'],
        ], null, 'A2');

        // Lembar petunjuk.
        $info = $ss->createSheet();
        $info->setTitle('Petunjuk');
        $lines = [
            'PETUNJUK PENGISIAN BANK SOAL',
            '',
            'Isi data mulai BARIS 2 di lembar "Soal". Baris contoh boleh dihapus.',
            '',
            '1.  Kode Mapel    : kode mata pelajaran, mis. MTK. WAJIB & harus mapel yang Anda ampu.',
            '2.  Tipe          : pilihan_ganda  atau  essay.',
            '3.  Kesulitan     : mudah / sedang / sulit.',
            '4.  Bobot         : angka poin soal (mis. 1).',
            '5.  Pertanyaan    : teks soal. WAJIB.',
            '6.  Gambar        : >>> ISI HANYA BILA SOAL ADA GAMBAR <<<',
            '                     CARA MUDAH: klik sel kolom Gambar, lalu sisipkan gambar',
            '                     (Excel: Insert > Pictures > Place in Cell). Gambar ikut saat di-import.',
            '                     Atau tulis NAMA FILE gambar yang sudah diunggah (mis. soal1.png).',
            '                     Kosongkan bila TIDAK ada gambar.',
            '7.  Video (URL)   : >>> ISI HANYA BILA SOAL ADA VIDEO <<<',
            '                     tempel URL video (mis. YouTube). Kosongkan bila TIDAK ada.',
            '8.  Opsi A s/d E  : pilihan jawaban (untuk pilihan_ganda). Boleh sampai 5 opsi.',
            '                     Kosongkan semua untuk soal essay.',
            '9.  Jawaban Benar : huruf opsi yang benar (A/B/C/D/E) — wajib untuk pilihan_ganda.',
            '10. Pembahasan    : penjelasan jawaban (opsional).',
            '11. Suara (nama file): >>> ISI HANYA BILA SOAL ADA AUDIO/SUARA <<<',
            '                     tulis NAMA FILE audio yang sudah diunggah (mis. listening1.mp3).',
            '                     Atau kosongkan & unggah lewat menu "Lengkapi Media" setelah import.',
            '',
            'CATATAN PENTING (agar tidak ada yang terlewat / slip):',
            '- Saat import, sistem MEMBERI PERINGATAN bila: gambar tidak ditemukan filenya,',
            '  kode mapel tidak dikenal, atau soal pilihan ganda tanpa jawaban benar.',
            '- Pastikan file gambar sudah diunggah lebih dulu sebelum menulis namanya di sini.',
        ];
        foreach ($lines as $i => $line) {
            $info->setCellValue('A'.($i + 1), $line);
        }
        $info->getColumnDimension('A')->setWidth(95);
        $info->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $info->getStyle('A20')->getFont()->setBold(true);

        $ss->setActiveSheetIndex(0);

        $writer = new Xlsx($ss);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'template-import-bank-soal.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    // ----------------------- IMPORT -----------------------

    /**
     * @param  array<int,int>|null  $allowedMapelIds  null = admin (semua mapel)
     * @return array{imported:int, with_image:int, with_video:int, with_audio:int, duplicates:int, batch:?string, warnings:array<int,string>}
     */
    public function import(string $filePath, ?int $createdBy, ?array $allowedMapelIds): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getSheetByName('Soal') ?? $spreadsheet->getSheet(0);
        $rows = $sheet->toArray(null, true, false, false);

        // Gambar yang ditanam langsung di sel (kolom Gambar), per baris.
        $embedded = $this->extractEmbeddedImages($sheet);

        $mapelByKode = [];
        $mapelNamaById = [];
        foreach (MataPelajaran::whereNotNull('kode')->get(['id', 'kode', 'nama']) as $m) {
            $mapelByKode[strtoupper(trim($m->kode))] = $m->id;
            $mapelNamaById[$m->id] = $m->nama;
        }

        $batch = (string) \Illuminate\Support\Str::uuid();

        $imported = 0;
        $withImage = 0;
        $withVideo = 0;
        $withAudio = 0;
        $duplicates = 0;
        $warnings = [];

        // Deteksi duplikat: tanda tangan isi soal (per mapel). Diisi dari soal yang
        // sudah ada di DB (lazy per mapel) lalu ditambah tiap soal baru yang dibuat,
        // sehingga duplikat antar-baris di file yang sama pun terdeteksi.
        $signatures = [];
        $loadedMapels = [];

        foreach ($rows as $idx => $r) {
            if ($idx === 0) {
                continue; // header
            }
            $r = array_pad($r, 15, null);
            [$kode, $tipe, $kesulitan, $bobot, $pertanyaan, $gambar, $video, $a, $b, $c, $d, $e, $benar, $pembahasan, $suara] = $r;
            $no = $idx + 1;

            if (blank($kode) && blank($pertanyaan)) {
                continue; // baris kosong
            }
            if (blank($pertanyaan)) {
                $warnings[] = "Baris {$no}: pertanyaan kosong — dilewati.";

                continue;
            }

            $mapelId = $mapelByKode[strtoupper(trim((string) $kode))] ?? null;
            if (! $mapelId) {
                $warnings[] = "Baris {$no}: kode mapel '".trim((string) $kode)."' tidak dikenal — dilewati.";

                continue;
            }
            if ($allowedMapelIds !== null && ! in_array($mapelId, $allowedMapelIds, true)) {
                $warnings[] = "Baris {$no}: mapel '".trim((string) $kode)."' bukan yang Anda ampu — dilewati.";

                continue;
            }

            // Label mapel untuk pesan peringatan: "MTK - Matematika".
            $mapelLabel = strtoupper(trim((string) $kode)).' - '.($mapelNamaById[$mapelId] ?? '?');

            $tipe = in_array(trim((string) $tipe), ['pilihan_ganda', 'essay'], true) ? trim((string) $tipe) : 'pilihan_ganda';
            $kesulitan = in_array(strtolower(trim((string) $kesulitan)), ['mudah', 'sedang', 'sulit'], true)
                ? strtolower(trim((string) $kesulitan)) : 'sedang';

            // Muat tanda tangan soal yang sudah ada untuk mapel ini (sekali per mapel).
            if (! isset($loadedMapels[$mapelId])) {
                foreach (Question::with('choices')->where('mata_pelajaran_id', $mapelId)->get() as $eq) {
                    $sig = $this->signature($eq->tipe->value, $eq->pertanyaan, $eq->choices->pluck('teks')->all());
                    $signatures[$mapelId][$sig] = true;
                }
                $loadedMapels[$mapelId] = true;
            }

            // Lewati bila soal identik (teks soal + opsi untuk PG; teks soal saja untuk essay).
            $sig = $this->signature($tipe, (string) $pertanyaan, [$a, $b, $c, $d, $e]);
            if (isset($signatures[$mapelId][$sig])) {
                $warnings[] = "Baris {$no} ({$mapelLabel}): soal duplikat (identik dengan soal yang sudah ada) — dilewati.";
                $duplicates++;

                continue;
            }
            $signatures[$mapelId][$sig] = true;

            // Apakah baris ini MENDEKLARASIKAN media (kolom Gambar/Video/Suara diisi / gambar tertanam)?
            $declaredImage = isset($embedded[$no]) || filled($gambar);
            $declaredVideo = filled($video);
            $declaredAudio = filled($suara);

            $gambarPath = null;
            if (isset($embedded[$no])) {
                // Gambar tertanam di file Excel -> simpan ke storage.
                $rel = 'soal/import-'.uniqid().'.'.$embedded[$no]['ext'];
                Storage::disk('public')->put($rel, $embedded[$no]['bytes']);
                $gambarPath = $rel;
                $withImage++;
            } elseif (filled($gambar)) {
                $fname = basename(trim((string) $gambar));
                $rel = 'soal/'.$fname;
                if (Storage::disk('public')->exists($rel)) {
                    $gambarPath = $rel;
                    $withImage++;
                } else {
                    $warnings[] = "Baris {$no} ({$mapelLabel}): gambar '{$fname}' tidak ditemukan — soal dibuat tanpa gambar.";
                }
            }

            $gotVideo = false;
            if ($declaredVideo) {
                $videoUrl = trim((string) $video);
                if (! preg_match('#^https?://#i', $videoUrl) || ! filter_var($videoUrl, FILTER_VALIDATE_URL)) {
                    $warnings[] = "Baris {$no} ({$mapelLabel}): URL video '{$videoUrl}' tidak valid — soal dibuat tanpa video.";
                    $video = null;
                } else {
                    $gotVideo = true;
                    $withVideo++;
                }
            }

            // Suara/audio: dideklarasikan dengan NAMA FILE yang sudah diunggah ke soal-audio/.
            $suaraPath = null;
            if ($declaredAudio) {
                $fname = basename(trim((string) $suara));
                $rel = 'soal-audio/'.$fname;
                if (Storage::disk('public')->exists($rel)) {
                    $suaraPath = $rel;
                    $withAudio++;
                } else {
                    $warnings[] = "Baris {$no} ({$mapelLabel}): suara '{$fname}' tidak ditemukan — soal dibuat tanpa suara.";
                }
            }

            // Soal menyatakan butuh media tapi belum berhasil dilampirkan -> tandai perlu dilengkapi.
            $mediaPending = ($declaredImage && $gambarPath === null)
                || ($declaredVideo && ! $gotVideo)
                || ($declaredAudio && $suaraPath === null);

            $question = Question::create([
                'mata_pelajaran_id' => $mapelId,
                'created_by' => $createdBy,
                'import_batch' => $batch,
                'tipe' => $tipe,
                'pertanyaan' => trim((string) $pertanyaan),
                'gambar' => $gambarPath,
                'video_url' => filled($video) ? trim((string) $video) : null,
                'suara' => $suaraPath,
                'media_pending' => $mediaPending,
                'bobot' => (int) ($bobot ?: 1),
                'tingkat_kesulitan' => $kesulitan,
                'pembahasan' => filled($pembahasan) ? trim((string) $pembahasan) : null,
            ]);

            if ($tipe === 'pilihan_ganda') {
                $opsi = ['A' => $a, 'B' => $b, 'C' => $c, 'D' => $d, 'E' => $e];
                $benarLabel = strtoupper(trim((string) $benar));
                $adaBenar = false;
                $urut = 1;
                foreach ($opsi as $label => $teks) {
                    if (blank($teks)) {
                        continue;
                    }
                    $isCorrect = $label === $benarLabel;
                    $adaBenar = $adaBenar || $isCorrect;
                    $question->choices()->create([
                        'label' => $label,
                        'teks' => trim((string) $teks),
                        'urutan' => $urut++,
                        'is_correct' => $isCorrect,
                    ]);
                }
                if (! $adaBenar) {
                    $warnings[] = "Baris {$no} ({$mapelLabel}): pilihan ganda tanpa jawaban benar — periksa kolom 'Jawaban Benar'.";
                }
            }

            $imported++;
        }

        return [
            'imported' => $imported,
            'with_image' => $withImage,
            'with_video' => $withVideo,
            'with_audio' => $withAudio,
            'duplicates' => $duplicates,
            'batch' => $imported > 0 ? $batch : null,
            'warnings' => $warnings,
        ];
    }

    /**
     * Tanda tangan isi soal untuk deteksi duplikat.
     * PG: teks soal + himpunan opsi (urutan diabaikan). Essay: teks soal saja.
     *
     * @param  array<int, mixed>  $opsiTeks
     */
    private function signature(string $tipe, string $pertanyaan, array $opsiTeks): string
    {
        $norm = static fn ($s): string => preg_replace('/\s+/u', ' ', trim(mb_strtolower((string) $s)));

        $key = $norm($pertanyaan);
        if ($tipe === 'pilihan_ganda') {
            $opts = array_values(array_filter(array_map($norm, $opsiTeks), static fn ($x) => $x !== ''));
            sort($opts);
            $key .= '|'.implode('|', $opts);
        }

        return $tipe.'::'.md5($key);
    }

    /**
     * Ambil gambar yang ditanam langsung di sel, dipetakan per nomor baris.
     *
     * @return array<int, array{bytes:string, ext:string}>
     */
    private function extractEmbeddedImages($sheet): array
    {
        $byRow = [];

        foreach ($sheet->getDrawingCollection() as $drawing) {
            try {
                $rowNum = (int) preg_replace('/\D/', '', (string) $drawing->getCoordinates());
                if ($rowNum < 2) {
                    continue;
                }

                if ($drawing instanceof \PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing) {
                    ob_start();
                    imagepng($drawing->getImageResource());
                    $bytes = (string) ob_get_clean();
                    $ext = 'png';
                } else {
                    $path = $drawing->getPath();   // zip://...#xl/media/imageN.ext
                    $bytes = (string) @file_get_contents($path);
                    $frag = str_contains($path, '#') ? substr($path, strpos($path, '#') + 1) : $path;
                    $ext = strtolower(pathinfo($frag, PATHINFO_EXTENSION));
                }

                if ($bytes !== '') {
                    $byRow[$rowNum] = [
                        'bytes' => $bytes,
                        'ext' => in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp'], true) ? $ext : 'png',
                    ];
                }
            } catch (\Throwable $e) {
                // Lewati gambar yang gagal dibaca.
            }
        }

        return $byRow;
    }
}
