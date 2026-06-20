<?php

namespace App\Filament\Resources\QuestionResource\Widgets;

use App\Filament\Resources\QuestionResource;
use App\Models\MataPelajaran;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

/**
 * Kartu pemilih mata pelajaran di atas tabel Bank Soal.
 * Klik sebuah kartu -> tabel menampilkan soal mapel tersebut.
 */
class MapelCards extends Widget
{
    protected static string $view = 'filament.resources.question-resource.widgets.mapel-cards';

    protected int|string|array $columnSpan = 'full';

    /**
     * @return Collection<int, MataPelajaran>
     */
    public function getMapels(): Collection
    {
        $query = MataPelajaran::query()
            ->withCount('questions')
            ->withCount(['questions as media_pending_count' => fn ($q) => $q->where('media_pending', true)]);

        $user = auth()->user();
        if ($user && $user->isGuru()) {
            $query->whereIn('id', $user->mataPelajaranIds());
        }

        return $query->orderBy('nama')->get();
    }

    public function getActiveMapel(): ?string
    {
        $mapel = request('mapel');

        return $mapel !== null ? (string) $mapel : null;
    }

    public function urlFor(int|string $mapel): string
    {
        return QuestionResource::getUrl('index', ['mapel' => $mapel]);
    }
}
