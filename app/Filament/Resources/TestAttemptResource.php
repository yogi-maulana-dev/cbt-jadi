<?php

namespace App\Filament\Resources;

use App\Enums\AttemptStatus;
use App\Filament\Resources\TestAttemptResource\Pages;
use App\Filament\Resources\TestAttemptResource\RelationManagers\AnswersRelationManager;
use App\Models\TestAttempt;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TestAttemptResource extends Resource
{
    protected static ?string $model = TestAttempt::class;

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->hasRole('guru', 'admin', 'superadmin');
    }

    /** Guru hanya MELIHAT hasil ujian; ubah/hapus hanya admin. */
    public static function canEdit($record): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public static function canDelete($record): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    /**
     * Operator (guru) hanya melihat hasil ujian pada mapel yang ditugaskan padanya.
     */
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();
        if ($user && $user->isGuru()) {
            $query->whereHas('test', fn ($q) => $q->whereIn('mata_pelajaran_id', $user->mataPelajaranIds()));
        }

        return $query;
    }

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Hasil Ujian';

    protected static ?string $modelLabel = 'Hasil Ujian';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('test_id')
                    ->relationship('test', 'judul')
                    ->label('Ujian')
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'name')
                    ->label('Siswa')
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\DateTimePicker::make('waktu_mulai'),
                Forms\Components\DateTimePicker::make('waktu_selesai'),
                Forms\Components\TextInput::make('skor')
                    ->numeric()
                    ->step('0.01'),
                Forms\Components\TextInput::make('jumlah_benar')
                    ->numeric()
                    ->default(0),
                Forms\Components\Select::make('status')
                    ->options(AttemptStatus::class)
                    ->default(AttemptStatus::SedangDikerjakan->value)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('test.judul')
                    ->label('Ujian')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Siswa')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('skor')
                    ->numeric(2)
                    ->sortable(),
                Tables\Columns\TextColumn::make('jumlah_benar')
                    ->label('Benar'),
                Tables\Columns\TextColumn::make('pelanggaran')
                    ->label('Pelanggaran')
                    ->badge()
                    ->color(fn ($state): string => $state > 0 ? 'danger' : 'gray')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge(),
                Tables\Columns\TextColumn::make('waktu_selesai')
                    ->dateTime()
                    ->sortable(),
            ])
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->emptyStateIcon('heroicon-o-chart-bar')
            ->emptyStateHeading('Pilih ujian')
            ->emptyStateDescription('Klik tombol "Lihat Hasil" pada salah satu kartu ujian di atas untuk menampilkan peserta & hasilnya.')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(AttemptStatus::class),
                Tables\Filters\Filter::make('ada_pelanggaran')
                    ->label('Hanya yang melanggar')
                    ->query(fn (\Illuminate\Database\Eloquent\Builder $query) => $query->where('pelanggaran', '>', 0)),
            ])
            ->actions([
                Tables\Actions\Action::make('keluarkan')
                    ->label('Keluarkan')
                    ->icon('heroicon-o-arrow-right-on-rectangle')
                    ->color('danger')
                    ->visible(fn (): bool => (bool) auth()->user()?->isAdmin())
                    ->requiresConfirmation()
                    ->modalHeading('Keluarkan & reset peserta ini?')
                    ->modalDescription('Sesi dan jawaban peserta ini akan DIHAPUS. Setelah soal diperbaiki, ia bisa memulai ujian lagi dari awal dengan soal terbaru.')
                    ->modalSubmitActionLabel('Ya, keluarkan')
                    ->action(function (TestAttempt $record) {
                        $record->delete(); // cascade: jawaban & snapshot soal ikut terhapus
                        Notification::make()
                            ->title('Peserta dikeluarkan')
                            ->body('Peserta direset & dapat memulai ujian lagi setelah soal diperbaiki.')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\EditAction::make()
                    ->visible(fn (): bool => (bool) auth()->user()?->isAdmin()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('keluarkan')
                        ->label('Keluarkan & reset')
                        ->icon('heroicon-o-arrow-right-on-rectangle')
                        ->color('danger')
                        ->visible(fn (): bool => (bool) auth()->user()?->isAdmin())
                        ->requiresConfirmation()
                        ->modalHeading('Keluarkan & reset peserta terpilih?')
                        ->modalDescription('Sesi dan jawaban peserta yang dipilih akan DIHAPUS. Setelah soal diperbaiki, mereka bisa memulai ujian lagi dari awal dengan soal terbaru.')
                        ->modalSubmitActionLabel('Ya, keluarkan')
                        ->action(function (\Illuminate\Support\Collection $records) {
                            $jumlah = $records->count();
                            // Hapus attempt -> jawaban & snapshot soal ikut terhapus (cascade).
                            $records->each(fn (TestAttempt $a) => $a->delete());

                            Notification::make()
                                ->title('Peserta dikeluarkan')
                                ->body("{$jumlah} peserta direset. Mereka dapat memulai ujian lagi setelah soal diperbaiki.")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn (): bool => (bool) auth()->user()?->isAdmin()),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            AnswersRelationManager::class,
        ];
    }

    /**
     * Attempt hanya dibuat oleh sistem saat siswa memulai ujian — bukan manual.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTestAttempts::route('/'),
            'edit' => Pages\EditTestAttempt::route('/{record}/edit'),
            'live' => Pages\LiveHasil::route('/live/{test}'),
            'riwayat' => Pages\RiwayatPelanggaran::route('/riwayat/{test}'),
        ];
    }
}
