<?php

namespace App\Filament\Resources;

use App\Enums\TestStatus;
use App\Filament\Resources\TestResource\Pages;
use App\Filament\Resources\TestResource\RelationManagers\QuestionsRelationManager;
use App\Models\Test;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class TestResource extends Resource
{
    protected static ?string $model = Test::class;

    public static function canViewAny(): bool
    {
        // Operator: lihat jadwal ujian (read-only). Admin: kelola penuh.
        return (bool) auth()->user()?->hasRole('operator', 'admin', 'superadmin');
    }

    /** Hanya admin yang boleh membuat/ubah/hapus ujian; operator hanya melihat. */
    public static function canCreate(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public static function canEdit($record): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public static function canDelete($record): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Ujian';

    protected static ?string $modelLabel = 'Ujian';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Ujian')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('mata_pelajaran_id')
                            ->relationship('mataPelajaran', 'nama')
                            ->label('Mata Pelajaran')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\Select::make('created_by')
                            ->relationship('creator', 'name')
                            ->label('Dibuat oleh')
                            ->default(auth()->id())
                            ->searchable()
                            ->preload(),
                        Forms\Components\TextInput::make('judul')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('deskripsi')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
                Forms\Components\Section::make('Pengaturan')
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('durasi')
                            ->label('Durasi (menit)')
                            ->numeric()
                            ->default(60)
                            ->required(),
                        Forms\Components\TextInput::make('kkm')
                            ->label('KKM (nilai minimal lulus)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->default(0),
                        Forms\Components\Select::make('status')
                            ->options(TestStatus::class)
                            ->default(TestStatus::Draft->value)
                            ->required(),
                        Forms\Components\TextInput::make('token')
                            ->label('Token akses')
                            ->maxLength(20),
                        Forms\Components\DateTimePicker::make('waktu_mulai')
                            ->label('Jadwal mulai'),
                        Forms\Components\DateTimePicker::make('waktu_selesai')
                            ->label('Jadwal selesai'),
                        Forms\Components\Toggle::make('acak_soal')
                            ->label('Acak soal'),
                        Forms\Components\Toggle::make('acak_jawaban')
                            ->label('Acak jawaban'),
                        Forms\Components\Toggle::make('tampilkan_hasil')
                            ->label('Tampilkan hasil ke siswa')
                            ->default(true),
                        Forms\Components\TextInput::make('max_pelanggaran')
                            ->label('Auto-submit setelah keluar tab (kali)')
                            ->helperText('0 = nonaktif')
                            ->numeric()
                            ->minValue(0)
                            ->default(0),
                    ]),
                Forms\Components\Section::make('Soal Ujian')
                    ->visible(fn (?Test $record): bool => $record !== null)
                    ->schema([
                        Forms\Components\Placeholder::make('soal_info')
                            ->hiddenLabel()
                            ->content('Soal disusun dari Bank Soal. Simpan ujian ini, lalu gunakan panel "Soal" di bawah untuk menautkan soal dari bank atau membuat soal baru.'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('judul')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('mataPelajaran.nama')
                    ->label('Mata Pelajaran')
                    ->sortable(),
                Tables\Columns\TextColumn::make('questions_count')
                    ->counts('questions')
                    ->label('Jml Soal'),
                Tables\Columns\TextColumn::make('durasi')
                    ->suffix(' mnt')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(TestStatus::class),
            ])
            ->actions([
                Tables\Actions\Action::make('ubahToken')
                    ->label('Ubah Token')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->visible(fn (): bool => (bool) auth()->user()?->isAdmin())
                    ->fillForm(fn (Test $record): array => ['token' => $record->token])
                    ->form([
                        Forms\Components\TextInput::make('token')
                            ->label('Token Akses')
                            ->maxLength(20)
                            ->helperText('Kosongkan untuk menonaktifkan token. Klik ikon untuk membuat token acak.')
                            ->suffixAction(
                                Forms\Components\Actions\Action::make('generate')
                                    ->icon('heroicon-m-arrow-path')
                                    ->tooltip('Buat token acak')
                                    ->action(fn (Forms\Set $set) => $set('token', Str::upper(Str::random(6)))),
                            ),
                    ])
                    ->modalHeading('Ubah Token Ujian')
                    ->modalSubmitActionLabel('Simpan Token')
                    ->action(fn (Test $record, array $data) => $record->update([
                        'token' => $data['token'] ?: null,
                    ]))
                    ->successNotificationTitle('Token ujian diperbarui'),
                Tables\Actions\Action::make('keluarkanPeserta')
                    ->label('Keluarkan Peserta')
                    ->icon('heroicon-o-arrow-right-on-rectangle')
                    ->color('danger')
                    ->visible(fn (Test $record): bool => (bool) auth()->user()?->isAdmin()
                        && $record->attempts()->where('status', \App\Enums\AttemptStatus::SedangDikerjakan)->exists())
                    ->requiresConfirmation()
                    ->modalHeading('Keluarkan SEMUA peserta yang sedang mengerjakan?')
                    ->modalDescription('Semua peserta yang sedang mengerjakan ujian ini akan dikeluarkan & sesinya direset. Hasil peserta yang sudah SELESAI tidak terhapus. Setelah soal diperbaiki, mereka bisa mulai lagi.')
                    ->modalSubmitActionLabel('Ya, keluarkan semua')
                    ->action(function (Test $record) {
                        $belumSelesai = $record->attempts()
                            ->where('status', \App\Enums\AttemptStatus::SedangDikerjakan);
                        $jumlah = $belumSelesai->count();
                        $belumSelesai->delete(); // cascade: jawaban & snapshot ikut terhapus

                        Notification::make()
                            ->title('Peserta dikeluarkan')
                            ->body("{$jumlah} peserta yang sedang mengerjakan direset. Mereka dapat mulai ujian lagi setelah soal diperbaiki.")
                            ->success()
                            ->send();
                    }),
                Tables\Actions\EditAction::make()
                    ->visible(fn (): bool => (bool) auth()->user()?->isAdmin()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn (): bool => (bool) auth()->user()?->isAdmin())
                        ->before(function (\Illuminate\Support\Collection $records, Tables\Actions\DeleteBulkAction $action) {
                            $aktif = $records->filter->hasActiveAttempts();
                            if ($aktif->isNotEmpty()) {
                                Notification::make()
                                    ->title('Sebagian ujian tidak bisa dihapus')
                                    ->body('Ujian "'.$aktif->pluck('judul')->implode('", "').'" masih dikerjakan siswa. Tutup ujian (Closed) lalu keluarkan/biarkan siswa selesai dulu.')
                                    ->danger()
                                    ->persistent()
                                    ->send();

                                $action->halt();
                            }
                        }),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            QuestionsRelationManager::class,
            \App\Filament\Resources\TestResource\RelationManagers\PengawasRelationManager::class,
            \App\Filament\Resources\TestResource\RelationManagers\PenempatanSiswaRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTests::route('/'),
            'create' => Pages\CreateTest::route('/create'),
            'edit' => Pages\EditTest::route('/{record}/edit'),
        ];
    }
}
