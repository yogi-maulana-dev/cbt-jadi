<?php

namespace App\Filament\Resources\TestAttemptResource\RelationManagers;

use App\Services\ScoringService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AnswersRelationManager extends RelationManager
{
    protected static string $relationship = 'answers';

    protected static ?string $title = 'Koreksi Essay';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Placeholder::make('pertanyaan')
                ->content(fn ($record) => $record?->question?->pertanyaan),
            Forms\Components\Placeholder::make('jawaban_essay')
                ->label('Jawaban siswa')
                ->content(fn ($record) => $record?->jawaban_essay ?: '(kosong)'),
            Forms\Components\TextInput::make('skor')
                ->label(fn ($record) => 'Skor (maks bobot: '.($record?->question?->bobot ?? 1).')')
                ->numeric()
                ->minValue(0)
                ->required(),
            Forms\Components\Toggle::make('is_correct')
                ->label('Tandai benar (opsional, untuk statistik)'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->whereHas(
                'question',
                fn (Builder $q) => $q->where('tipe', 'essay')
            ))
            ->columns([
                Tables\Columns\TextColumn::make('question.pertanyaan')
                    ->label('Soal')
                    ->limit(50)
                    ->wrap(),
                Tables\Columns\TextColumn::make('jawaban_essay')
                    ->label('Jawaban')
                    ->limit(60)
                    ->wrap(),
                Tables\Columns\TextColumn::make('question.bobot')
                    ->label('Bobot'),
                Tables\Columns\TextColumn::make('skor')
                    ->label('Skor')
                    ->placeholder('belum dinilai')
                    ->badge()
                    ->color(fn ($state) => $state === null ? 'warning' : 'success'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Nilai')
                    ->after(fn () => app(ScoringService::class)
                        ->recalculate($this->getOwnerRecord())),
            ]);
    }

    /**
     * Sembunyikan tab koreksi bila ujian ini tak punya soal essay.
     */
    public function isReadOnly(): bool
    {
        return false;
    }
}
