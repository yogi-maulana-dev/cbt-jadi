<?php

namespace App\Filament\Resources\QuestionResource\Pages;

use App\Filament\Resources\QuestionResource;
use App\Filament\Resources\QuestionResource\Widgets\MapelCards;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

class ListQuestions extends ListRecords
{
    protected static string $resource = QuestionResource::class;

    protected static string $view = 'filament.resources.question-resource.pages.list-questions';

    /** Mapel yang dipilih lewat kartu: null = belum pilih, "all" = semua, selain itu = ID mapel. */
    #[Url]
    public ?string $mapel = null;

    protected function getHeaderWidgets(): array
    {
        return [
            MapelCards::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    /**
     * Tabel hanya menampilkan soal setelah sebuah mata pelajaran dipilih dari kartu.
     */
    protected function getTableQuery(): ?Builder
    {
        $query = parent::getTableQuery();

        if ($query !== null) {
            if (blank($this->mapel)) {
                $query->whereRaw('1 = 0'); // belum pilih -> tabel kosong
            } elseif ($this->mapel !== 'all') {
                $query->where('mata_pelajaran_id', $this->mapel);
            }
        }

        return $query;
    }
}
