<?php

namespace App\Filament\Resources\TestAttemptResource\Widgets;

use App\Enums\AttemptStatus;
use App\Filament\Resources\TestAttemptResource;
use App\Models\Test;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

/**
 * Kartu pemilih ujian di atas tabel Hasil Ujian.
 * Klik sebuah kartu -> tabel menampilkan peserta/hasil ujian tersebut.
 */
class UjianCards extends Widget
{
    protected static string $view = 'filament.resources.test-attempt-resource.widgets.ujian-cards';

    protected int|string|array $columnSpan = 'full';

    /** Kata kunci pencarian ujian (live). */
    public string $search = '';

    /**
     * @return Collection<int, Test>
     */
    public function getUjian(): Collection
    {
        return Test::query()
            ->with('mataPelajaran:id,nama,kode')
            ->withCount('attempts')
            ->withCount(['attempts as sedang_count' => fn ($q) => $q->where('status', AttemptStatus::SedangDikerjakan)])
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($qq) use ($term) {
                    $qq->where('judul', 'like', $term)
                        ->orWhereHas('mataPelajaran', fn ($m) => $m->where('nama', 'like', $term)->orWhere('kode', 'like', $term));
                });
            })
            ->orderByDesc('id')
            ->limit(60)
            ->get();
    }

    public function getActiveTest(): ?string
    {
        $test = request('test');

        return $test !== null ? (string) $test : null;
    }

    public function urlFor(int|string $test): string
    {
        return TestAttemptResource::getUrl('index', ['test' => $test]);
    }

    public function liveUrlFor(int|string $test): string
    {
        return TestAttemptResource::getUrl('live', ['test' => $test]);
    }

    public function riwayatUrlFor(int|string $test): string
    {
        return TestAttemptResource::getUrl('riwayat', ['test' => $test]);
    }
}
