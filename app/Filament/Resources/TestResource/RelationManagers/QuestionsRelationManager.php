<?php

namespace App\Filament\Resources\TestResource\RelationManagers;

use App\Filament\Resources\QuestionResource;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class QuestionsRelationManager extends RelationManager
{
    protected static string $relationship = 'questions';

    protected static ?string $title = 'Soal';

    protected static ?string $modelLabel = 'Soal';

    public function form(Form $form): Form
    {
        // Pakai ulang skema field dari QuestionResource (bank soal).
        return $form->schema(QuestionResource::questionFields());
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('pertanyaan')
            ->reorderable('urutan')
            ->defaultSort('test_question.urutan')
            ->columns([
                Tables\Columns\TextColumn::make('urutan')
                    ->label('#')
                    ->state(fn ($record): int => $record->pivot->urutan),
                Tables\Columns\TextColumn::make('pertanyaan')
                    ->limit(70)
                    ->searchable(),
                Tables\Columns\TextColumn::make('tipe')
                    ->badge(),
                Tables\Columns\TextColumn::make('tingkat_kesulitan')
                    ->label('Kesulitan')
                    ->badge(),
                Tables\Columns\TextColumn::make('bobot')
                    ->label('Bobot')
                    ->state(fn ($record): int => $record->pivot->bobot ?? $record->bobot),
            ])
            ->headerActions([
                // Tautkan soal yang sudah ada di bank.
                Tables\Actions\AttachAction::make()
                    ->label('Tautkan dari Bank')
                    ->preloadRecordSelect()
                    ->multiple()
                    ->recordSelectSearchColumns(['pertanyaan']),
                // Buat soal baru sekaligus masuk ke bank dan tertaut ke ujian ini.
                Tables\Actions\CreateAction::make()
                    ->label('Buat Soal Baru')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['mata_pelajaran_id'] ??= $this->getOwnerRecord()->mata_pelajaran_id;
                        $data['created_by'] ??= auth()->id();

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DetachAction::make()
                    ->label('Lepas'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DetachBulkAction::make(),
                ]),
            ]);
    }
}
