<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ChoiceResource\Pages;
use App\Models\Choice;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ChoiceResource extends Resource
{
    protected static ?string $model = Choice::class;

    protected static ?string $navigationIcon = 'heroicon-o-list-bullet';

    protected static ?string $navigationLabel = 'Pilihan Jawaban';

    protected static ?string $modelLabel = 'Pilihan Jawaban';

    // Pilihan jawaban umumnya dikelola lewat form Soal (Question).
    // Sembunyikan dari menu agar tidak membingungkan; ubah ke true bila ingin
    // mengelolanya secara terpisah.
    protected static bool $shouldRegisterNavigation = false;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('question_id')
                    ->relationship('question', 'pertanyaan')
                    ->label('Soal')
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\TextInput::make('label')
                    ->maxLength(2)
                    ->placeholder('A'),
                Forms\Components\Textarea::make('teks')
                    ->label('Teks jawaban')
                    ->required()
                    ->rows(2),
                Forms\Components\Toggle::make('is_correct')
                    ->label('Jawaban benar'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('question.pertanyaan')
                    ->label('Soal')
                    ->limit(40)
                    ->searchable(),
                Tables\Columns\TextColumn::make('label'),
                Tables\Columns\TextColumn::make('teks')
                    ->limit(40),
                Tables\Columns\IconColumn::make('is_correct')
                    ->label('Benar')
                    ->boolean(),
            ])
            ->filters([
                //
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListChoices::route('/'),
            'create' => Pages\CreateChoice::route('/create'),
            'edit' => Pages\EditChoice::route('/{record}/edit'),
        ];
    }
}
