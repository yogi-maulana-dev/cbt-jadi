<?php

namespace App\Filament\Resources\TestResource\Pages;

use App\Enums\TestStatus;
use App\Filament\Resources\TestResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditTest extends EditRecord
{
    protected static string $resource = TestResource::class;

    protected function getHeaderActions(): array
    {
        return [
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
}
