<?php

namespace App\Filament\Resources\TestAttemptResource\Pages;

use App\Filament\Resources\TestAttemptResource;
use App\Models\PelanggaranLog;
use App\Models\Test;
use Filament\Actions;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Riwayat blokir/pelanggaran siswa pada sebuah ujian (untuk evaluasi) + export Excel.
 */
class RiwayatPelanggaran extends Page
{
    protected static string $resource = TestAttemptResource::class;

    protected static string $view = 'filament.resources.test-attempt-resource.pages.riwayat-pelanggaran';

    protected static bool $shouldRegisterNavigation = false;

    public int $testId;

    public string $judul = '';

    public function mount(string $test): void
    {
        $t = Test::findOrFail($test);
        $this->testId = $t->id;
        $this->judul = $t->judul;
    }

    /**
     * @return Collection<int, PelanggaranLog>
     */
    public function getLogs(): Collection
    {
        return PelanggaranLog::query()
            ->where('test_id', $this->testId)
            ->with(['user:id,name', 'dibukaOleh:id,name'])
            ->orderByDesc('diblokir_pada')
            ->get();
    }

    /**
     * Ringkasan per siswa: jumlah insiden & berapa kali dibuka.
     *
     * @return Collection<int, array{nama:string, insiden:int, dibuka:int}>
     */
    public function getRingkasan(): Collection
    {
        return $this->getLogs()
            ->groupBy('user_id')
            ->map(fn (Collection $g): array => [
                'nama' => optional($g->first()->user)->name ?? '—',
                'insiden' => $g->count(),
                'dibuka' => $g->whereNotNull('dibuka_pada')->count(),
            ])
            ->sortByDesc('insiden')
            ->values();
    }

    public function getTitle(): string
    {
        return 'Riwayat Pelanggaran: '.$this->judul;
    }

    public function getBreadcrumb(): string
    {
        return 'Riwayat Pelanggaran';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('export')
                ->label('Export Excel')
                ->icon('heroicon-o-table-cells')
                ->color('success')
                ->action(fn (): StreamedResponse => $this->exportExcel()),
        ];
    }

    public function exportExcel(): StreamedResponse
    {
        $logs = $this->getLogs();

        $ss = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle('Riwayat Pelanggaran');

        $headers = ['No', 'Siswa', 'Ujian', 'Alasan', 'Diblokir Pada', 'Dibuka Pada', 'Dibuka Oleh'];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:G1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A1:G1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('DC2626');
        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setWidth(20);
        }
        $sheet->getColumnDimension('D')->setWidth(40);

        $row = 2;
        foreach ($logs as $i => $log) {
            $sheet->setCellValue("A{$row}", $i + 1);
            $sheet->setCellValue("B{$row}", optional($log->user)->name ?? '—');
            $sheet->setCellValue("C{$row}", $log->test_judul ?? $this->judul);
            $sheet->setCellValue("D{$row}", $log->alasan ?? '');
            $sheet->setCellValue("E{$row}", optional($log->diblokir_pada)->format('d/m/Y H:i') ?? '');
            $sheet->setCellValue("F{$row}", optional($log->dibuka_pada)->format('d/m/Y H:i') ?? 'Masih diblokir');
            $sheet->setCellValue("G{$row}", optional($log->dibukaOleh)->name ?? '');
            $row++;
        }

        $writer = new Xlsx($ss);
        $filename = 'riwayat-pelanggaran-'.date('Ymd-His').'.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
