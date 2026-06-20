<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RuanganResource\Pages;
use App\Models\Ruangan;
use App\Services\RuanganImport;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class RuanganResource extends Resource
{
    protected static ?string $model = Ruangan::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationLabel = 'Ruangan';

    protected static ?string $modelLabel = 'Ruangan';

    protected static ?string $pluralModelLabel = 'Ruangan';

    protected static ?string $navigationGroup = 'Pengaturan';

    /** Hanya admin/superadmin yang boleh mengelola ruangan. */
    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('nama')
                ->label('Nama Ruangan')
                ->required()
                ->maxLength(60)
                ->unique(ignoreRecord: true),
            Forms\Components\TextInput::make('kapasitas')
                ->label('Kapasitas')
                ->numeric()
                ->minValue(0)
                ->maxValue(65535)
                ->suffix('kursi / komputer')
                ->helperText('Jumlah peserta maksimal di ruangan ini. Boleh dikosongkan.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nama')->label('Nama Ruangan')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('kapasitas')->label('Kapasitas')->numeric()->sortable()->placeholder('—')->badge()->color('info'),
                Tables\Columns\TextColumn::make('created_at')->label('Dibuat')->dateTime('d/m/Y H:i')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('nama')
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('Tambah Ruangan'),
                Tables\Actions\Action::make('unduhTemplate')
                    ->label('Unduh Template')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->action(fn () => app(RuanganImport::class)->template()),
                Tables\Actions\Action::make('importExcel')
                    ->label('Import Excel')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('warning')
                    ->modalHeading('Import Ruangan dari Excel')
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
                        $report = app(RuanganImport::class)->import($file->getRealPath());

                        $notif = Notification::make()->title('Import selesai')->persistent();
                        $body = "Berhasil impor/perbarui {$report['imported']} ruangan.";
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
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            'index' => Pages\ManageRuangans::route('/'),
        ];
    }
}
