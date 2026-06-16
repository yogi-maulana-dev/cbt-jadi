<?php

namespace App\Filament\Resources;

use App\Enums\QuestionType;
use App\Filament\Resources\QuestionResource\Pages;
use App\Models\Jurusan;
use App\Models\Question;
use App\Models\User;
use App\Services\BankSoalExport;
use App\Services\BankSoalImport;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class QuestionResource extends Resource
{
    protected static ?string $model = Question::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationLabel = 'Bank Soal';

    protected static ?string $modelLabel = 'Soal';

    protected static ?string $pluralModelLabel = 'Bank Soal';

    /**
     * Guru (operator) hanya melihat soal mapel yang ditugaskan padanya.
     * Admin melihat semua.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user && $user->role === 'operator') {
            $query->whereIn('mata_pelajaran_id', $user->mataPelajaranIds());
        }

        return $query;
    }

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
                        ->relationship('mataPelajaran', 'nama', function (Builder $query) {
                            $user = auth()->user();
                            if ($user && $user->role === 'operator') {
                                $query->whereIn('id', $user->mataPelajaranIds());
                            }
                        })
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
                    Forms\Components\TextInput::make('video_url')
                        ->label('Video via URL (opsional)')
                        ->url()
                        ->prefixIcon('heroicon-o-video-camera')
                        ->placeholder('https://youtu.be/...')
                        ->helperText('Tempel URL video (mis. YouTube), atau unggah file video di bawah.')
                        ->columnSpanFull(),
                    Forms\Components\FileUpload::make('video_path')
                        ->label('Video via file (opsional)')
                        ->directory('soal-video')
                        ->acceptedFileTypes(['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime', 'video/x-msvideo', 'video/x-matroska', 'video/3gpp'])
                        ->maxSize(51200)
                        ->helperText('Seret file video ke sini. Maks 50 MB. Format mp4 paling disarankan.')
                        ->columnSpanFull(),
                    Forms\Components\FileUpload::make('suara')
                        ->label('Suara / audio (opsional)')
                        ->directory('soal-audio')
                        ->acceptedFileTypes(['audio/mpeg', 'audio/mp3', 'audio/ogg', 'audio/wav', 'audio/x-wav', 'audio/aac', 'audio/mp4', 'audio/x-m4a'])
                        ->maxSize(20480)
                        ->helperText('Seret file audio (mp3/ogg/wav) ke sini. Maks 20 MB. Berguna untuk soal listening.')
                        ->columnSpanFull(),
                    Forms\Components\Toggle::make('media_pending')
                        ->label('Tandai: soal ini wajib bergambar/video tetapi belum lengkap')
                        ->helperText('Bila aktif, ujian yang memuat soal ini tidak bisa dipublish sampai medianya dilengkapi. Otomatis nonaktif begitu gambar/video diisi.')
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
                Tables\Columns\IconColumn::make('video_url')
                    ->label('Video')
                    ->boolean()
                    ->trueIcon('heroicon-o-video-camera')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('info')
                    ->falseColor('gray'),
                Tables\Columns\IconColumn::make('media_pending')
                    ->label('Media')
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('danger')
                    ->falseColor('success')
                    ->tooltip(fn (bool $state): string => $state ? 'Perlu gambar/video — belum lengkap' : 'Media lengkap / tidak perlu'),
                Tables\Columns\TextColumn::make('pertanyaan')
                    ->limit(60)
                    ->color(fn (Question $record): ?string => $record->media_pending ? 'danger' : null)
                    ->weight(fn (Question $record): ?\Filament\Support\Enums\FontWeight => $record->media_pending ? \Filament\Support\Enums\FontWeight::Bold : null)
                    ->tooltip(fn (Question $record): ?string => $record->media_pending ? 'Belum ada gambar/video' : null)
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
                Tables\Filters\SelectFilter::make('jurusan')
                    ->label('Jurusan')
                    ->options(fn (): array => Jurusan::orderBy('nama')->pluck('nama', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('mataPelajaran', fn ($q) => $q->where('jurusan_id', $data['value']))
                        : $query)
                    ->visible(fn (): bool => (bool) auth()->user()?->isAdmin()),
                Tables\Filters\SelectFilter::make('guru')
                    ->label('Guru')
                    ->options(fn (): array => User::where('role', 'operator')->orderBy('name')->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('mataPelajaran.gurus', fn ($q) => $q->where('users.id', $data['value']))
                        : $query)
                    ->visible(fn (): bool => (bool) auth()->user()?->isAdmin()),
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
                Tables\Filters\TernaryFilter::make('media_pending')
                    ->label('Status media')
                    ->placeholder('Semua soal')
                    ->trueLabel('Belum lengkap (perlu gambar/video)')
                    ->falseLabel('Sudah lengkap / tidak perlu'),
            ])
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(10)
            ->emptyStateIcon('heroicon-o-rectangle-stack')
            ->emptyStateHeading('Pilih mata pelajaran')
            ->emptyStateDescription('Klik tombol "Lihat Soal" pada salah satu kartu mata pelajaran di atas untuk menampilkan soalnya.')
            ->headerActions([
                Tables\Actions\Action::make('lengkapiMedia')
                    ->label(fn (): string => 'Lengkapi Media ('.static::pendingMediaCount().')')
                    ->icon('heroicon-o-photo')
                    ->color('danger')
                    ->visible(fn (): bool => static::pendingMediaBatch() !== null)
                    ->url(fn (): ?string => ($b = static::pendingMediaBatch()) ? static::getUrl('lengkapi-media', ['batch' => $b]) : null),
                Tables\Actions\Action::make('unduhTemplate')
                    ->label('Unduh Template')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->action(fn () => app(BankSoalImport::class)->template()),
                Tables\Actions\Action::make('importExcel')
                    ->label('Import Excel')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('warning')
                    ->modalHeading('Import Bank Soal dari Excel')
                    ->modalDescription('Gunakan template (tombol "Unduh Template"). Unggah file .xlsx yang sudah diisi.')
                    ->form([
                        Forms\Components\FileUpload::make('file')
                            ->label('File Excel (.xlsx)')
                            ->acceptedFileTypes([
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'application/vnd.ms-excel',
                            ])
                            ->storeFiles(false)
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        $file = is_array($data['file']) ? reset($data['file']) : $data['file'];
                        $user = auth()->user();
                        $allowed = ($user && $user->role === 'operator') ? $user->mataPelajaranIds() : null;

                        $report = app(BankSoalImport::class)->import($file->getRealPath(), $user?->id, $allowed);

                        $dup = $report['duplicates'] ?? 0;
                        $aud = $report['with_audio'] ?? 0;
                        $body = "Berhasil impor {$report['imported']} soal — gambar {$report['with_image']}, video {$report['with_video']}, suara {$aud}, duplikat dilewati {$dup}.";

                        $notif = Notification::make()->title('Import selesai')->persistent();

                        if (! empty($report['warnings'])) {
                            $list = collect($report['warnings'])->take(15)->map(fn ($w) => e($w))->implode('<br>');
                            $more = count($report['warnings']) > 15 ? '<br>… dan peringatan lainnya' : '';
                            $notif->warning()->body(new HtmlString($body.'<br><br><strong>Peringatan:</strong><br>'.$list.$more));
                        } else {
                            $notif->success()->body($body);
                        }

                        // Tombol lanjut ke halaman melengkapi gambar/video soal hasil import.
                        if (! empty($report['batch'])) {
                            $notif->actions([
                                \Filament\Notifications\Actions\Action::make('lengkapiMedia')
                                    ->label('Lengkapi gambar & video')
                                    ->button()
                                    ->url(static::getUrl('lengkapi-media', ['batch' => $report['batch']])),
                            ]);
                        }

                        $notif->send();
                    }),
                Tables\Actions\Action::make('exportExcel')
                    ->label('Export Excel')
                    ->icon('heroicon-o-table-cells')
                    ->color('success')
                    ->action(fn ($livewire) => app(BankSoalExport::class)->excel(
                        $livewire->getFilteredTableQuery()->with(['choices', 'mataPelajaran.jurusan'])->get()
                    )),
                Tables\Actions\Action::make('exportWord')
                    ->label('Export Word')
                    ->icon('heroicon-o-document-text')
                    ->color('info')
                    ->action(fn ($livewire) => app(BankSoalExport::class)->word(
                        $livewire->getFilteredTableQuery()->with(['choices', 'mataPelajaran.jurusan'])->get()
                    )),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function (\Illuminate\Support\Collection $records, Tables\Actions\DeleteBulkAction $action) {
                            $aktif = $records->filter->inActiveExam();
                            if ($aktif->isNotEmpty()) {
                                Notification::make()
                                    ->title('Sebagian soal tidak bisa dihapus')
                                    ->body($aktif->count().' soal sedang dipakai pada ujian yang aktif dikerjakan siswa. Tutup/selesaikan ujian itu dulu.')
                                    ->danger()
                                    ->persistent()
                                    ->send();

                                $action->halt();
                            }
                        }),
                ]),
            ]);
    }

    /**
     * Batch import TERAKHIR yang masih punya soal belum lengkap media (untuk tombol "Lengkapi Media").
     * Dibatasi ke soal milik operator yang bersangkutan.
     */
    public static function pendingMediaBatch(): ?string
    {
        $query = Question::query()
            ->whereNotNull('import_batch')
            ->where('media_pending', true);

        $user = auth()->user();
        if ($user && $user->role === 'operator') {
            $query->where('created_by', $user->id);
        }

        return $query->orderByDesc('id')->value('import_batch');
    }

    /**
     * Jumlah soal belum lengkap media pada batch tersebut (untuk label tombol).
     */
    public static function pendingMediaCount(): int
    {
        $batch = static::pendingMediaBatch();
        if (! $batch) {
            return 0;
        }

        return Question::where('import_batch', $batch)->where('media_pending', true)->count();
    }

    /**
     * Total seluruh soal yang belum lengkap media (untuk badge menu), dibatasi milik operator.
     */
    public static function pendingMediaTotal(): int
    {
        $query = Question::query()->where('media_pending', true);

        $user = auth()->user();
        if ($user && $user->role === 'operator') {
            $query->where('created_by', $user->id);
        }

        return $query->count();
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::pendingMediaTotal();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Soal yang belum lengkap gambar/video';
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListQuestions::route('/'),
            'create' => Pages\CreateQuestion::route('/create'),
            'edit' => Pages\EditQuestion::route('/{record}/edit'),
            'lengkapi-media' => Pages\LengkapiMedia::route('/lengkapi-media/{batch}'),
        ];
    }
}
