<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export Bank Soal ke Excel (dengan gambar) & Word (format tabel).
 */
class BankSoalExport
{
    /** Path file gambar di disk publik, atau null. */
    private function imagePath(?string $gambar): ?string
    {
        if (! $gambar) {
            return null;
        }
        $path = Storage::disk('public')->path($gambar);

        return is_file($path) ? $path : null;
    }

    private function jawabanText($question): string
    {
        return $question->choices
            ->map(fn ($c) => ($c->is_correct ? '✔ ' : '').($c->label ? $c->label.'. ' : '').$c->teks)
            ->implode("\n");
    }

    // ----------------------- EXCEL -----------------------

    public function excel(Collection $questions): StreamedResponse
    {
        $ss = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle('Soal');

        // Layout SAMA dengan template import -> hasil export bisa diedit & di-import ulang.
        $sheet->fromArray(BankSoalImport::HEADERS, null, 'A1');

        $sheet->getStyle('A1:O1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A1:O1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4F46E5');
        $sheet->getStyle('A1:O1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        foreach (range('A', 'O') as $col) {
            $sheet->getColumnDimension($col)->setWidth(15);
        }
        $sheet->getColumnDimension('E')->setWidth(42); // Pertanyaan
        $sheet->getColumnDimension('F')->setWidth(20); // Gambar
        $sheet->getColumnDimension('N')->setWidth(28); // Pembahasan
        $sheet->getColumnDimension('O')->setWidth(22); // Suara

        $optCols = ['H', 'I', 'J', 'K', 'L'];

        $row = 2;
        foreach ($questions as $q) {
            $sheet->setCellValue("A{$row}", optional($q->mataPelajaran)->kode ?? '');
            $sheet->setCellValue("B{$row}", $q->tipe->value);
            $sheet->setCellValue("C{$row}", $q->tingkat_kesulitan);
            $sheet->setCellValue("D{$row}", $q->bobot);
            $sheet->setCellValue("E{$row}", $q->pertanyaan);
            $sheet->setCellValue("G{$row}", $q->video_url ?? '');

            $benar = '';
            foreach ($q->choices->values() as $idx => $c) {
                if ($idx > 4) {
                    break;
                }
                $sheet->setCellValue($optCols[$idx]."{$row}", $c->teks);
                if ($c->is_correct) {
                    $benar = $c->label ?: chr(65 + $idx);
                }
            }
            $sheet->setCellValue("M{$row}", $benar);
            $sheet->setCellValue("N{$row}", $q->pembahasan ?? '');
            $sheet->setCellValue("O{$row}", $q->suara ? basename($q->suara) : '');

            // Gambar ditanam langsung di sel (kolom F) -> ikut saat di-import lagi.
            $img = $this->imagePath($q->gambar);
            if ($img) {
                $drawing = new Drawing();
                $drawing->setPath($img);
                $drawing->setHeight(78);
                $drawing->setCoordinates("F{$row}");
                $drawing->setOffsetX(3);
                $drawing->setOffsetY(3);
                $drawing->setWorksheet($sheet);
                $sheet->getRowDimension($row)->setRowHeight(66);
            }

            $sheet->getStyle("A{$row}:O{$row}")->getAlignment()
                ->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);

            $row++;
        }

        $sheet->getStyle('A1:O'.($row - 1))->getBorders()->getAllBorders()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

        $writer = new Xlsx($ss);
        $filename = 'bank-soal-'.date('Ymd-His').'.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    // ----------------------- WORD -----------------------

    public function word(Collection $questions): StreamedResponse
    {
        $word = new PhpWord();
        $section = $word->addSection();
        $section->addText('BANK SOAL', ['bold' => true, 'size' => 16]);
        $section->addTextBreak();

        $table = $section->addTable([
            'borderSize' => 6, 'borderColor' => '999999', 'cellMargin' => 60,
        ]);

        $headerStyle = ['bold' => true, 'color' => 'FFFFFF'];
        $headerCell = ['bgColor' => '4F46E5', 'valign' => 'center'];
        $table->addRow();
        $cols = ['No' => 500, 'Mapel' => 1500, 'Soal' => 3400, 'Gambar' => 1600, 'Video' => 1800, 'Jawaban' => 2500, 'Pembahasan' => 2300];
        foreach ($cols as $label => $w) {
            $table->addCell($w, $headerCell)->addText($label, $headerStyle);
        }

        foreach ($questions as $i => $q) {
            $table->addRow();
            $table->addCell(500)->addText((string) ($i + 1));
            $table->addCell(1500)->addText(optional($q->mataPelajaran)->nama ?? '');
            $table->addCell(3400)->addText($q->pertanyaan);

            $imgCell = $table->addCell(1600);
            $img = $this->imagePath($q->gambar);
            if ($img) {
                $imgCell->addImage($img, ['width' => 110, 'height' => 85]);
            } else {
                $imgCell->addText('-');
            }

            $table->addCell(1800)->addText($q->video_url ?: '-');

            $jawCell = $table->addCell(2500);
            foreach ($q->choices as $c) {
                $jawCell->addText(
                    ($c->is_correct ? '✔ ' : '').($c->label ? $c->label.'. ' : '').$c->teks,
                    $c->is_correct ? ['bold' => true] : []
                );
            }

            $table->addCell(2300)->addText($q->pembahasan ?? '');
        }

        $writer = IOFactory::createWriter($word, 'Word2007');
        $filename = 'bank-soal-'.date('Ymd-His').'.docx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
    }
}
