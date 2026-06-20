<?php

namespace App\Filament\Resources\TestResource\Pages;

use App\Enums\TestStatus;
use App\Filament\Resources\TestResource;
use App\Services\JadwalOtomatisService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\HtmlString;

class EditTest extends EditRecord
{
    protected static string $resource = TestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('aturRuanganOtomatis')
                ->label('Atur Ruangan Otomatis')
                ->icon('heroicon-o-sparkles')
                ->color('info')
                ->modalHeading('Atur Ruangan Otomatis')
                ->modalDescription('Bagi siswa ke ruangan sesuai kapasitas dan/atau tugaskan pengawas ke tiap ruangan. Hasil tetap bisa diubah manual.')
                ->form([
                    Forms\Components\Toggle::make('tempatkan_siswa')
                        ->label('Tempatkan semua siswa ke ruangan')
                        ->default(true)
                        ->helperText('Penempatan siswa lama untuk jadwal ini akan ditata ulang.'),
                    Forms\Components\Toggle::make('tugaskan_pengawas')
                        ->label('Tugaskan pengawas ke tiap ruangan')
                        ->default(true)
                        ->helperText('Mengisi ruangan yang belum ada pengawas; yang sudah ada tidak diubah.'),
                ])
                ->action(function (array $data): void {
                    $report = app(JadwalOtomatisService::class)->generate(
                        $this->record,
                        (bool) ($data['tempatkan_siswa'] ?? false),
                        (bool) ($data['tugaskan_pengawas'] ?? false),
                    );

                    $body = "Ruangan terpakai: {$report['ruangan']}, siswa ditempatkan: {$report['siswa']}, pengawas ditugaskan: {$report['pengawas']}.";

                    $notif = Notification::make()->title('Atur ruangan otomatis selesai')->persistent();
                    if (! empty($report['warnings'])) {
                        $list = collect($report['warnings'])->map(fn ($w) => e($w))->implode('<br>');
                        $notif->warning()->body(new HtmlString($body.'<br><br><strong>Catatan:</strong><br>'.$list));
                    } else {
                        $notif->success()->body($body);
                    }
                    $notif->send();
                }),
            Actions\DeleteAction::make()
                ->before(function (Actions\DeleteAction $action) {
                    if ($this->record->hasActiveAttempts()) {
                        Notification::make()
                            ->title('Ujian tidak bisa dihapus')
                            ->body('Masih ada '.$this->record->activeAttemptsCount().' siswa yang sedang mengerjakan. Tutup ujian (status Closed) lalu tunggu/keluarkan siswa yang masih mengerjakan dulu.')
                            ->danger()
                            ->persistent()
                            ->send();

                        $action->halt();
                    }
                }),
        ];
    }

    /**
     * Cegah publish ujian bila masih ada soal yang ditandai perlu gambar/video
     * tetapi medianya belum dilengkapi.
     */
    protected function beforeSave(): void
    {
        if (($this->data['status'] ?? null) !== TestStatus::Published->value) {
            return;
        }

        $pending = $this->record->questions()
            ->where('media_pending', true)
            ->with('mataPelajaran:id,nama')
            ->get();

        if ($pending->isEmpty()) {
            return;
        }

        $daftar = $pending
            ->map(fn ($q): string => 'No. '.($q->pivot->urutan ?? '?').' ('.(optional($q->mataPelajaran)->nama ?? 'mapel?').')')
            ->implode(', ');

        Notification::make()
            ->title('Ujian belum bisa dipublish')
            ->body('Soal berikut belum ada gambar/video: '.$daftar.'. Lengkapi dulu medianya, lalu publish lagi.')
            ->danger()
            ->persistent()
            ->send();

        $this->halt();
    }

    /**
     * Setelah jadwal/waktu diubah, peringatkan bila pengawas bentrok dengan jadwal lain.
     */
    protected function afterSave(): void
    {
        $konflik = $this->record->konflikPengawas();

        if (! empty($konflik)) {
            Notification::make()
                ->title('Perhatian: pengawas bentrok jadwal')
                ->body(implode("\n", $konflik))
                ->warning()
                ->persistent()
                ->send();
        }
    }
}
