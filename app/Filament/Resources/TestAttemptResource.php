<?php

namespace App\Filament\Resources;

use App\Enums\AttemptStatus;
use App\Filament\Resources\TestAttemptResource\Pages;
use App\Filament\Resources\TestAttemptResource\RelationManagers\AnswersRelationManager;
use App\Models\TestAttempt;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TestAttemptResource extends Resource
{
    protected static ?string $model = TestAttempt::class;

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
            ->filters([
                Tables\Filters\SelectFilter::make('test_id')
                    ->relationship('test', 'judul')
                    ->label('Ujian'),
                Tables\Filters\SelectFilter::make('status')
                    ->options(AttemptStatus::class),
                Tables\Filters\Filter::make('ada_pelanggaran')
                    ->label('Hanya yang melanggar')
                    ->query(fn (\Illuminate\Database\Eloquent\Builder $query) => $query->where('pelanggaran', '>', 0)),
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
        ];
    }
}
