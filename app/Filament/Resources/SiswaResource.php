<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SiswaResource\Pages;
use App\Models\User;
use App\Services\StudentImport;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class SiswaResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationLabel = 'Siswa';

    protected static ?string $modelLabel = 'Siswa';

    protected static ?string $pluralModelLabel = 'Siswa';

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('role', 'siswa');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Hidden::make('role')->default('siswa'),
            Forms\Components\TextInput::make('no_ujian')->label('No Ujian')->required()->unique(ignoreRecord: true)->maxLength(50)
                ->helperText('Dipakai untuk login. Password awal = No Ujian.'),
            Forms\Components\TextInput::make('name')->label('Nama')->required()->maxLength(120),
            Forms\Components\TextInput::make('kelas')->label('Kelas')->maxLength(50),
            Forms\Components\TextInput::make('program_studi')->label('Program Studi')->maxLength(120),
            Forms\Components\TextInput::make('email')->email()->unique(ignoreRecord: true)->maxLength(150)
                ->helperText('Opsional. Bila kosong dibuat otomatis; siswa mengisi email aktif saat login pertama.'),
            Forms\Components\TextInput::make('password')
                ->label('Password')
                ->password()
                ->revealable()
                ->dehydrated(fn (?string $state): bool => filled($state))
                ->helperText('Kosongkan: password = No Ujian (siswa wajib ganti saat login pertama).'),
            Forms\Components\Toggle::make('aktif')
                ->label('Akun aktif')
                ->default(true)
                ->helperText('Nonaktif = siswa tidak bisa login (beda dari blokir pelanggaran ujian).'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('no_ujian')->label('No Ujian')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('kelas')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('program_studi')->label('Program Studi')->searchable(),
                Tables\Columns\TextColumn::make('email')->searchable()->toggleable(),
                Tables\Columns\ToggleColumn::make('aktif')->label('Aktif'),
                Tables\Columns\IconColumn::make('diblokir')
                    ->label('Blokir Ujian')
                    ->boolean()
                    ->trueColor('danger')
                    ->falseColor('gray'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('kelas')
                    ->options(fn (): array => User::where('role', 'siswa')->whereNotNull('kelas')->distinct()->orderBy('kelas')->pluck('kelas', 'kelas')->all()),
            ])
            ->headerActions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('cetakSemuaPdf')
                        ->label('Cetak Semua (PDF)')
                        ->icon('heroicon-o-printer')
                        ->url(fn (): string => route('kartu.ujian', ['all' => 1]), shouldOpenInNewTab: true),
                    Tables\Actions\Action::make('cetakSemuaWord')
                        ->label('Download Semua (Word)')
                        ->icon('heroicon-o-document-text')
                        ->url(fn (): string => route('kartu.word', ['all' => 1]), shouldOpenInNewTab: true),
                    Tables\Actions\Action::make('cetakPerKelas')
                        ->label('Cetak per Kelas')
                        ->icon('heroicon-o-rectangle-group')
                        ->form([
                            Forms\Components\Select::make('kelas')
                                ->label('Kelas')
                                ->options(fn (): array => User::where('role', 'siswa')->whereNotNull('kelas')->distinct()->orderBy('kelas')->pluck('kelas', 'kelas')->all())
                                ->required(),
                            Forms\Components\Radio::make('format')
                                ->options(['pdf' => 'Cetak / PDF', 'word' => 'Word (.docx)'])
                                ->default('pdf')
                                ->required(),
                        ])
                        ->action(fn (array $data) => redirect()->route(
                            $data['format'] === 'word' ? 'kartu.word' : 'kartu.ujian',
                            ['kelas' => $data['kelas']],
                        )),
                ])->label('Cetak Kartu')->icon('heroicon-o-printer')->color('success')->button(),
                Tables\Actions\Action::make('unduhTemplate')
                    ->label('Unduh Template')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->action(fn () => app(StudentImport::class)->template()),
                Tables\Actions\Action::make('importExcel')
                    ->label('Import Excel')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('warning')
                    ->modalHeading('Import Siswa dari Excel')
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
                        $report = app(StudentImport::class)->import($file->getRealPath());

                        $notif = Notification::make()->title('Import selesai')->persistent();
                        $body = "Berhasil impor/perbarui {$report['imported']} siswa.";
                        if (! empty($report['warnings'])) {
                            $list = collect($report['warnings'])->take(15)->map(fn ($w) => e($w))->implode('<br>');
                            $notif->warning()->body(new HtmlString($body.'<br><br><strong>Peringatan:</strong><br>'.$list));
                        } else {
                            $notif->success()->body($body);
                        }
                        $notif->send();
                    }),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('cetakKartu')
                        ->label('Cetak / PDF')
                        ->icon('heroicon-o-printer')
                        ->url(fn (User $record): string => route('kartu.ujian', ['ids' => $record->id]), shouldOpenInNewTab: true),
                    Tables\Actions\Action::make('kartuWord')
                        ->label('Download Word')
                        ->icon('heroicon-o-document-text')
                        ->url(fn (User $record): string => route('kartu.word', ['ids' => $record->id]), shouldOpenInNewTab: true),
                    Tables\Actions\EditAction::make(),
                ])->label('Aksi')->icon('heroicon-m-ellipsis-vertical')->button(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('cetakKartu')
                        ->label('Cetak Kartu (PDF)')
                        ->icon('heroicon-o-printer')
                        ->color('success')
                        ->action(fn ($records) => redirect()->route('kartu.ujian', ['ids' => $records->pluck('id')->implode(',')]))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('kartuWord')
                        ->label('Download Kartu (Word)')
                        ->icon('heroicon-o-document-text')
                        ->color('info')
                        ->action(fn ($records) => redirect()->route('kartu.word', ['ids' => $records->pluck('id')->implode(',')]))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('aktifkan')
                        ->label('Aktifkan akun')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(fn ($records) => $records->each->update(['aktif' => true]))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('nonaktifkan')
                        ->label('Nonaktifkan akun')
                        ->icon('heroicon-o-no-symbol')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each->update(['aktif' => false]))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSiswa::route('/'),
            'create' => Pages\CreateSiswa::route('/create'),
            'edit' => Pages\EditSiswa::route('/{record}/edit'),
        ];
    }
}
