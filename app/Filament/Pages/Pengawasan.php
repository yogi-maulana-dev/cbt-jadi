<?php

namespace App\Filament\Pages;

use App\Models\Test;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Dashboard pengawas: token tiap jadwal ujian yang diawasi (real-time) + ganti token.
 */
class Pengawasan extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-eye';

    protected static ?string $navigationLabel = 'Pengawasan';

    protected static ?string $title = 'Pengawasan Ujian';

    protected static string $view = 'filament.pages.pengawasan';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasRole('pengawas', 'admin', 'superadmin');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) auth()->user()?->hasRole('pengawas', 'admin', 'superadmin');
    }

    /**
     * @return Collection<int, Test>
     */
    public function getJadwal(): Collection
    {
        $user = auth()->user();

        if ($user->isPengawas()) {
            return $user->ujianDiawasi()->with('mataPelajaran')->orderByDesc('id')->get();
        }

        // Admin: semua jadwal yang punya pengawas.
        return Test::query()->whereHas('pengawas')->with(['mataPelajaran', 'pengawas'])->orderByDesc('id')->get();
    }

    public function gantiToken(int $testId): void
    {
        $test = Test::find($testId);
        if (! $test) {
            return;
        }

        $user = auth()->user();
        // Pengawas hanya boleh untuk ujian yang ia awasi; admin bebas.
        if ($user->isPengawas() && ! $test->pengawas()->where('users.id', $user->id)->exists()) {
            return;
        }

        $test->update(['token' => Str::upper(Str::random(6))]);

        Notification::make()
            ->title('Token diganti')
            ->body('Token ujian "'.$test->judul.'" diperbarui menjadi '.$test->token.'.')
            ->success()
            ->send();
    }
}
