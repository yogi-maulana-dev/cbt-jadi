<?php

namespace App\Filament\Resources\TestResource\RelationManagers;

use App\Models\Ruangan;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PenempatanSiswaRelationManager extends RelationManager
{
    protected static string $relationship = 'penempatanSiswa';

    protected static ?string $title = 'Penempatan Siswa';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('user_id')
                ->label('Siswa')
                ->required()
                ->searchable()
                ->options(fn (?Model $record): array => $this->siswaOptions($record))
                ->rule(function (?Model $record) {
                    return function (string $attribute, $value, \Closure $fail) use ($record): void {
                        if (blank($value)) {
                            return;
                        }
                        $query = $this->getOwnerRecord()->penempatanSiswa()->where('user_id', $value);
                        if ($record) {
                            $query->where('id', '!=', $record->getKey());
                        }
                        if ($query->exists()) {
                            $fail('Siswa ini sudah ditempatkan di jadwal ini.');
                        }
                    };
                }),
            $this->ruanganField(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('siswa.name')->label('Nama Siswa')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('siswa.kelas')->label('Kelas')->searchable(),
                Tables\Columns\TextColumn::make('ruangan.nama')->label('Ruangan')->badge()->color('info')->sortable(),
            ])
            ->defaultSort('ruangan_id')
            ->filters([
                Tables\Filters\SelectFilter::make('ruangan_id')
                    ->label('Ruangan')
                    ->options(fn (): array => Ruangan::pluck('nama', 'id')->all()),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('Tambah Penempatan'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('Pindah Ruangan'),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Field ruangan (Select baku) dengan validasi tidak melebihi kapasitas.
     */
    protected function ruanganField(): Forms\Components\Select
    {
        return Forms\Components\Select::make('ruangan_id')
            ->label('Ruangan')
            ->required()
            ->searchable()
            ->options(fn (): array => Ruangan::orderByRaw('LENGTH(nama), nama')
                ->get(['id', 'nama', 'kapasitas'])
                ->mapWithKeys(fn (Ruangan $r): array => [
                    $r->id => $r->kapasitas ? "{$r->nama} (kap. {$r->kapasitas})" : $r->nama,
                ])->all())
            ->rule(function (?Model $record) {
                return function (string $attribute, $value, \Closure $fail) use ($record): void {
                    if (blank($value)) {
                        return;
                    }
                    $room = Ruangan::find($value);
                    if (! $room || ! $room->kapasitas) {
                        return; // tanpa kapasitas = tak ada batas
                    }
                    $terisi = $this->getOwnerRecord()->penempatanSiswa()
                        ->where('ruangan_id', $value)
                        ->when($record, fn ($q) => $q->where('id', '!=', $record->getKey()))
                        ->count();
                    if ($terisi >= $room->kapasitas) {
                        $fail("Ruang {$room->nama} sudah penuh (kapasitas {$room->kapasitas}).");
                    }
                };
            });
    }

    /**
     * Pilihan siswa: belum ditempatkan di jadwal ini (kecuali yang sedang diedit).
     *
     * @return array<int, string>
     */
    protected function siswaOptions(?Model $record): array
    {
        $terpakai = $this->getOwnerRecord()->penempatanSiswa()
            ->when($record, fn ($q) => $q->where('id', '!=', $record->getKey()))
            ->pluck('user_id');

        return User::where('role', 'siswa')
            ->whereNotIn('id', $terpakai)
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (User $u): array => [
                $u->id => $u->kelas ? "{$u->name} ({$u->kelas})" : $u->name,
            ])->all();
    }
}
