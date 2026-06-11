<?php

namespace App\Filament\Resources\TestResource\RelationManagers;

use App\Filament\Resources\QuestionResource;
use Filament\Forms;
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
                Tables\Columns\TextColumn::make('cadangan')
                    ->label('Jenis')
                    ->state(fn ($record): string => $record->pivot->cadangan ? 'Cadangan' : 'Utama')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Cadangan' ? 'warning' : 'success'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('cadangan')
                    ->label('Soal cadangan')
                    ->queries(
                        true: fn ($query) => $query->wherePivot('cadangan', true),
                        false: fn ($query) => $query->wherePivot('cadangan', false),
                        blank: fn ($query) => $query,
                    ),
            ])
            ->headerActions([
                // Tautkan soal yang sudah ada di bank (bisa ditandai cadangan).
                Tables\Actions\AttachAction::make()
                    ->label('Tautkan dari Bank')
                    ->preloadRecordSelect()
                    ->multiple()
                    ->recordSelectSearchColumns(['pertanyaan'])
                    ->form(fn (Tables\Actions\AttachAction $action): array => [
                        $action->getRecordSelect(),
                        Forms\Components\Toggle::make('cadangan')
                            ->label('Tandai sebagai soal cadangan')
                            ->default(false),
                    ]),
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
                Tables\Actions\Action::make('toggleCadangan')
                    ->label(fn ($record): string => $record->pivot->cadangan ? 'Jadikan Utama' : 'Jadikan Cadangan')
                    ->icon('heroicon-o-arrows-right-left')
                    ->color('warning')
                    ->action(fn ($record) => $this->getOwnerRecord()->questions()
                        ->updateExistingPivot($record->id, ['cadangan' => ! $record->pivot->cadangan])),
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
