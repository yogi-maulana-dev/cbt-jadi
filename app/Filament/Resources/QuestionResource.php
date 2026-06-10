<?php

namespace App\Filament\Resources;

use App\Enums\QuestionType;
use App\Filament\Resources\QuestionResource\Pages;
use App\Models\Question;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class QuestionResource extends Resource
{
    protected static ?string $model = Question::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationLabel = 'Bank Soal';

    protected static ?string $modelLabel = 'Soal';

    protected static ?string $pluralModelLabel = 'Bank Soal';

    public static function form(Form $form): Form
    {
        return $form->schema(static::questionFields());
    }

    /**
     * Skema field soal, dipakai ulang di RelationManager ujian.
     *
     * @return array<int, \Filament\Forms\Components\Component>
     */
    public static function questionFields(): array
    {
        return [
            Forms\Components\Section::make('Soal')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('mata_pelajaran_id')
                        ->relationship('mataPelajaran', 'nama')
                        ->label('Mata Pelajaran')
                        ->searchable()
                        ->preload()
                        ->required(),
                    Forms\Components\Select::make('tipe')
                        ->options(QuestionType::class)
                        ->default(QuestionType::PilihanGanda->value)
                        ->live()
                        ->required(),
                    Forms\Components\Select::make('tingkat_kesulitan')
                        ->options([
                            'mudah' => 'Mudah',
                            'sedang' => 'Sedang',
                            'sulit' => 'Sulit',
                        ])
                        ->default('sedang')
                        ->required(),
                    Forms\Components\TextInput::make('bobot')
                        ->numeric()
                        ->default(1)
                        ->minValue(1)
                        ->required(),
                    Forms\Components\Textarea::make('pertanyaan')
                        ->required()
                        ->rows(3)
                        ->columnSpanFull(),
                    Forms\Components\FileUpload::make('gambar')
                        ->label('Gambar soal (opsional)')
                        ->image()
                        ->directory('soal')
                        ->imageEditor()
                        ->openable()
                        ->downloadable()
                        ->maxSize(2048)
                        ->helperText('Seret gambar ke sini atau klik untuk memilih. Maks 2 MB.')
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('pembahasan')
                        ->rows(2)
                        ->columnSpanFull(),
                ]),
            Forms\Components\Section::make('Pilihan Jawaban')
                ->description('Centang "Benar" pada opsi jawaban yang benar. Boleh lebih dari 4 opsi. Seret ⠿ untuk mengurutkan.')
                ->visible(fn (Forms\Get $get): bool => $get('tipe') === QuestionType::PilihanGanda->value)
                ->schema([
                    Forms\Components\Repeater::make('choices')
                        ->relationship()
                        ->label('Opsi')
                        ->schema([
                            Forms\Components\TextInput::make('label')
                                ->label('Label')
                                ->maxLength(2)
                                ->placeholder('A')
                                ->columnSpan(1),
                            Forms\Components\Textarea::make('teks')
                                ->label('Teks jawaban')
                                ->rows(1)
                                ->required()
                                ->columnSpan(3),
                            Forms\Components\FileUpload::make('gambar')
                                ->label('Gambar')
                                ->image()
                                ->directory('opsi')
                                ->imageEditor()
                                ->maxSize(2048)
                                ->columnSpan(1),
                            Forms\Components\Toggle::make('is_correct')
                                ->label('Benar')
                                ->inline(false)
                                ->columnSpan(1),
                        ])
                        ->columns(6)
                        ->defaultItems(4)
                        ->minItems(2)
                        ->addActionLabel('Tambah opsi')
                        ->orderColumn('urutan')
                        ->reorderable()
                        ->collapsible()
                        ->rule(static function () {
                            return static function (string $attribute, $value, \Closure $fail): void {
                                $adaBenar = collect($value)->contains(fn ($opsi) => (bool) ($opsi['is_correct'] ?? false));

                                if (! $adaBenar) {
                                    $fail('Minimal satu opsi harus ditandai sebagai jawaban benar.');
                                }
                            };
                        }),
                ]),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('mataPelajaran.nama')
                    ->label('Mapel')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\ImageColumn::make('gambar')
                    ->label('Gambar')
                    ->height(40)
                    ->square(),
                Tables\Columns\TextColumn::make('pertanyaan')
                    ->limit(60)
                    ->searchable(),
                Tables\Columns\TextColumn::make('tipe')
                    ->badge(),
                Tables\Columns\TextColumn::make('tingkat_kesulitan')
                    ->label('Kesulitan')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'mudah' => 'success',
                        'sedang' => 'warning',
                        'sulit' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('bobot')
                    ->sortable(),
                Tables\Columns\TextColumn::make('choices_count')
                    ->counts('choices')
                    ->label('Jml Opsi'),
                Tables\Columns\TextColumn::make('tests_count')
                    ->counts('tests')
                    ->label('Dipakai di')
                    ->suffix(' ujian'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('mata_pelajaran_id')
                    ->relationship('mataPelajaran', 'nama')
                    ->label('Mapel'),
                Tables\Filters\SelectFilter::make('tingkat_kesulitan')
                    ->options([
                        'mudah' => 'Mudah',
                        'sedang' => 'Sedang',
                        'sulit' => 'Sulit',
                    ]),
                Tables\Filters\SelectFilter::make('tipe')
                    ->options(QuestionType::class),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListQuestions::route('/'),
            'create' => Pages\CreateQuestion::route('/create'),
            'edit' => Pages\EditQuestion::route('/{record}/edit'),
        ];
    }
}
