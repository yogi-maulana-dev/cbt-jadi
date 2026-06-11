<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SiswaDiblokirResource\Pages;
use App\Models\User;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SiswaDiblokirResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-lock-closed';

    protected static ?string $navigationLabel = 'Siswa Diblokir';

    protected static ?string $modelLabel = 'Siswa Diblokir';

    protected static ?string $pluralModelLabel = 'Siswa Diblokir';

    /**
     * Hanya tampilkan siswa yang sedang diblokir.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('diblokir', true);
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('diblokir', true)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('role')
                    ->badge(),
                Tables\Columns\TextColumn::make('alasan_blokir')
                    ->label('Alasan')
                    ->wrap()
                    ->placeholder('—')
                    ->color('danger'),
                Tables\Columns\TextColumn::make('diblokir_pada')
                    ->label('Diblokir pada')
                    ->dateTime('d M Y, H:i')
                    ->description(fn ($record): ?string => $record->diblokir_pada?->diffForHumans())
                    ->sortable(),
            ])
            ->defaultSort('diblokir_pada', 'desc')
            ->actions([
                Tables\Actions\Action::make('bukaBlokir')
                    ->label('Buka Blokir')
                    ->icon('heroicon-o-lock-open')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Buka blokir siswa?')
                    ->modalDescription('Siswa akan bisa login kembali.')
                    ->action(fn (User $record) => $record->bukaBlokir())
                    ->successNotificationTitle('Blokir dibuka, siswa bisa login lagi'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('bukaBlokirMassal')
                        ->label('Buka Blokir')
                        ->icon('heroicon-o-lock-open')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each->bukaBlokir())
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->emptyStateHeading('Tidak ada siswa yang diblokir')
            ->emptyStateIcon('heroicon-o-check-circle');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSiswaDiblokirs::route('/'),
        ];
    }
}
