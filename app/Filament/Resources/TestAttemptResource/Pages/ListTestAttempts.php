<?php

namespace App\Filament\Resources\TestAttemptResource\Pages;

use App\Filament\Resources\TestAttemptResource;
use App\Filament\Resources\TestAttemptResource\Widgets\UjianCards;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

class ListTestAttempts extends ListRecords
{
    protected static string $resource = TestAttemptResource::class;

    protected static string $view = 'filament.resources.test-attempt-resource.pages.list-test-attempts';

    /** Ujian yang dipilih lewat kartu: null = belum pilih, "all" = semua, selain itu = ID ujian. */
    #[Url]
    public ?string $test = null;

    protected function getHeaderWidgets(): array
    {
        return [
            UjianCards::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    /**
     * Tabel hasil hanya tampil setelah sebuah ujian dipilih dari kartu.
     */
    protected function getTableQuery(): ?Builder
    {
        $query = parent::getTableQuery();

        if ($query !== null) {
            if (blank($this->test)) {
                $query->whereRaw('1 = 0'); // belum pilih -> kosong
            } elseif ($this->test !== 'all') {
                $query->where('test_id', $this->test);
            }
        }

        return $query;
    }
}
