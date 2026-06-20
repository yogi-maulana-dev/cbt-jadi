<?php

namespace App\Services;

use App\Models\Ruangan;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Import data ruangan dari Excel.
 */
class RuanganImport
{
    public const HEADERS = ['Nama Ruangan', 'Kapasitas'];

    public function template(): StreamedResponse
    {
        $ss = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle('Ruangan');
        $sheet->fromArray(self::HEADERS, null, 'A1');
        $sheet->getStyle('A1:B1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A1:B1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4F46E5');
        foreach (range('A', 'B') as $col) {
            $sheet->getColumnDimension($col)->setWidth(24);
        }

        $sheet->fromArray([
            ['Ruang 1', 40],
            ['Lab Komputer A', 36],
        ], null, 'A2');

        $info = $ss->createSheet();
        $info->setTitle('Petunjuk');
        foreach ([
            'PETUNJUK IMPORT RUANGAN',
            '',
            '1. Nama Ruangan : nama ruangan (WAJIB, unik).',
            '2. Kapasitas    : jumlah kursi/komputer (angka, boleh dikosongkan).',
            '',
            'Catatan: baris dengan Nama Ruangan sama akan diperbarui (bukan dobel).',
        ] as $i => $line) {
            $info->setCellValue('A'.($i + 1), $line);
        }
        $info->getColumnDimension('A')->setWidth(90);
        $info->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $ss->setActiveSheetIndex(0);
        $writer = new Xlsx($ss);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'template-import-ruangan.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @return array{imported:int, warnings:array<int,string>}
     */
    public function import(string $filePath): array
    {
        $loaded = IOFactory::load($filePath);
        $sheet = $loaded->getSheetByName('Ruangan') ?? $loaded->getSheet(0);
        $rows = $sheet->toArray(null, true, false, false);

        $imported = 0;
        $warnings = [];

        foreach ($rows as $idx => $r) {
            if ($idx === 0) {
                continue; // header
            }
            $r = array_pad($r, 2, null);
            [$nama, $kapasitas] = $r;
            $no = $idx + 1;

            if (blank($nama)) {
                if (filled($kapasitas)) {
                    $warnings[] = "Baris {$no}: Nama Ruangan kosong — dilewati.";
                }

                continue; // baris kosong
            }

            $nama = trim((string) $nama);

            $kap = null;
            if (filled($kapasitas)) {
                if (! is_numeric($kapasitas) || (int) $kapasitas < 0) {
                    $warnings[] = "Baris {$no} ({$nama}): Kapasitas \"{$kapasitas}\" bukan angka — dikosongkan.";
                } else {
                    $kap = (int) $kapasitas;
                }
            }

            Ruangan::updateOrCreate(['nama' => $nama], ['kapasitas' => $kap]);
            $imported++;
        }

        return ['imported' => $imported, 'warnings' => $warnings];
    }
}
