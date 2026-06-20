<?php

namespace App\Filament\Resources\RuanganResource\Pages;

use App\Filament\Resources\RuanganResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageRuangans extends ManageRecords
{
    protected static string $resource = RuanganResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Tambah Ruangan'),
        ];
    }
}
