<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Import data siswa dari Excel.
 */
class StudentImport
{
    public const HEADERS = ['No Ujian', 'Nama', 'Kelas', 'Program Studi'];

    public function template(): StreamedResponse
    {
        $ss = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle('Siswa');
        $sheet->fromArray(self::HEADERS, null, 'A1');
        $sheet->getStyle('A1:D1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A1:D1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4F46E5');
        foreach (range('A', 'D') as $col) {
            $sheet->getColumnDimension($col)->setWidth(24);
        }

        $sheet->fromArray([
            ['2024001', 'Budi Santoso', 'XII RPL 1', 'Rekayasa Perangkat Lunak'],
            ['2024002', 'Siti Aminah', 'XII TKJ 2', 'Teknik Komputer dan Jaringan'],
        ], null, 'A2');

        $info = $ss->createSheet();
        $info->setTitle('Petunjuk');
        foreach ([
            'PETUNJUK IMPORT SISWA',
            '',
            '1. No Ujian     : nomor ujian siswa (WAJIB, dipakai untuk LOGIN).',
            '2. Nama         : nama lengkap siswa (WAJIB).',
            '3. Kelas        : mis. XII RPL 1.',
            '4. Program Studi: mis. Rekayasa Perangkat Lunak.',
            '',
            'Login siswa: No Ujian sebagai username, dan PASSWORD = No Ujian (sama).',
            'Saat login pertama, siswa WAJIB mengganti password & mengisi email aktif.',
            'Catatan: baris dengan No Ujian sama akan diperbarui (bukan dobel).',
        ] as $i => $line) {
            $info->setCellValue('A'.($i + 1), $line);
        }
        $info->getColumnDimension('A')->setWidth(90);
        $info->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $ss->setActiveSheetIndex(0);
        $writer = new Xlsx($ss);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'template-import-siswa.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @return array{imported:int, warnings:array<int,string>}
     */
    public function import(string $filePath): array
    {
        $sheet = IOFactory::load($filePath)->getSheetByName('Siswa') ?? IOFactory::load($filePath)->getSheet(0);
        $rows = $sheet->toArray(null, true, false, false);

        $imported = 0;
        $warnings = [];

        foreach ($rows as $idx => $r) {
            if ($idx === 0) {
                continue; // header
            }
            $r = array_pad($r, 4, null);
            [$noUjian, $nama, $kelas, $prodi] = $r;
            $no = $idx + 1;

            if (blank($noUjian) && blank($nama)) {
                continue; // baris kosong
            }
            if (blank($nama)) {
                $warnings[] = "Baris {$no}: nama kosong — dilewati.";

                continue;
            }
            if (blank($noUjian)) {
                $warnings[] = "Baris {$no}: No Ujian kosong — dilewati.";

                continue;
            }

            $noUjian = trim((string) $noUjian);
            $existing = User::where('no_ujian', $noUjian)->first();

            $attrs = [
                'name' => trim((string) $nama),
                'kelas' => filled($kelas) ? trim((string) $kelas) : null,
                'program_studi' => filled($prodi) ? trim((string) $prodi) : null,
                'role' => 'siswa',
            ];

            if ($existing) {
                // Perbarui data; password & email siswa yang sudah ada tidak diubah.
                $existing->update($attrs);
            } else {
                // Baru: email placeholder, password = No Ujian, wajib ganti saat login pertama.
                User::create($attrs + [
                    'no_ujian' => $noUjian,
                    'email' => $noUjian.'@ujian.local',
                    'password' => Hash::make($noUjian),
                    'must_change_password' => true,
                ]);
            }

            $imported++;
        }

        return ['imported' => $imported, 'warnings' => $warnings];
    }
}
