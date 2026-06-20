<?php

namespace App\Filament\Resources\TestResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PengawasRelationManager extends RelationManager
{
    protected static string $relationship = 'pengawas';

    protected static ?string $title = 'Pengawas Ujian';

    protected static ?string $recordTitleAttribute = 'name';

    public function form(Form $form): Form
    {
        return $form->schema([
            $this->ruanganField(),
        ]);
    }

    /**
     * Field ruangan: pilihan baku dari data master Ruangan (bisa tambah baru
     * inline), wajib diisi & tidak boleh sama dengan pengawas lain pada jadwal
     * (ujian) yang sama.
     */
    protected function ruanganField(): Forms\Components\Select
    {
        return Forms\Components\Select::make('ruangan')
            ->label('Ruangan')
            ->required()
            ->searchable()
            ->options(fn (): array => \App\Models\Ruangan::pilihan())
            ->createOptionForm([
                Forms\Components\TextInput::make('nama')
                    ->label('Nama Ruangan')
                    ->required()
                    ->maxLength(60)
                    ->unique('ruangans', 'nama'),
            ])
            ->createOptionUsing(fn (array $data): string => \App\Models\Ruangan::create($data)->nama)
            ->createOptionModalHeading('Tambah Ruangan Baru')
            ->rule(function (?\Illuminate\Database\Eloquent\Model $record) {
                return function (string $attribute, $value, \Closure $fail) use ($record): void {
                    if (blank($value)) {
                        return;
                    }

                    $query = $this->getOwnerRecord()->pengawas()
                        ->wherePivot('ruangan', $value);

                    // Saat edit, abaikan baris pengawas yang sedang diubah.
                    if ($record) {
                        $query->where('users.id', '!=', $record->getKey());
                    }

                    if ($query->exists()) {
                        $fail("Ruangan \"{$value}\" sudah diisi pengawas lain pada jadwal ini.");
                    }
                };
            });
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Pengawas')->searchable(),
                Tables\Columns\TextColumn::make('pivot.ruangan')->label('Ruangan'),
                Tables\Columns\TextColumn::make('role')->badge(),
            ])
            ->headerActions([
                Tables\Actions\AttachAction::make()
                    ->label('Tambah Pengawas')
                    ->preloadRecordSelect()
                    ->recordSelectOptionsQuery(fn (Builder $query) => $query->whereIn('role', ['guru', 'pengawas']))
                    ->recordSelectSearchColumns(['name', 'email'])
                    ->form(fn (Tables\Actions\AttachAction $action): array => [
                        $action->getRecordSelect()->label('Guru / Pengawas'),
                        $this->ruanganField(),
                    ])
                    ->after(function (): void {
                        $konflik = $this->getOwnerRecord()->fresh()->konflikPengawas();
                        if (! empty($konflik)) {
                            \Filament\Notifications\Notification::make()
                                ->title('Perhatian: pengawas bentrok jadwal')
                                ->body(implode("\n", $konflik))
                                ->warning()
                                ->persistent()
                                ->send();
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('Ubah Ruangan'),
                Tables\Actions\DetachAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DetachBulkAction::make(),
                ]),
            ]);
    }
}
