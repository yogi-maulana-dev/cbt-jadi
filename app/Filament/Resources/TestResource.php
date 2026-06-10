<?php

namespace App\Filament\Resources;

use App\Enums\TestStatus;
use App\Filament\Resources\TestResource\Pages;
use App\Filament\Resources\TestResource\RelationManagers\QuestionsRelationManager;
use App\Models\Test;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TestResource extends Resource
{
    protected static ?string $model = Test::class;

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
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            QuestionsRelationManager::class,
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
