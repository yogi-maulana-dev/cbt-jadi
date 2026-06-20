<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GuruResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class GuruResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationLabel = 'Guru';

    protected static ?string $modelLabel = 'Guru';

    protected static ?string $pluralModelLabel = 'Guru';

    protected static ?string $navigationGroup = 'Pengaturan';

    /** Hanya admin/superadmin yang boleh mengelola guru. */
    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    /** Menu ini khusus menampilkan akun guru. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('role', 'guru');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Hidden::make('role')->default('guru'),
            Forms\Components\TextInput::make('name')
                ->label('Nama')
                ->required()
                ->maxLength(120),
            Forms\Components\TextInput::make('email')
                ->label('Email')
                ->email()
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(150),
            Forms\Components\TextInput::make('password')
                ->label('Password')
                ->password()
                ->revealable()
                ->minLength(8)
                ->maxLength(255)
                ->required(fn (string $operation): bool => $operation === 'create')
                ->dehydrated(fn (?string $state): bool => filled($state))
                ->same('password_confirmation')
                ->validationAttribute('password')
                ->helperText('Minimal 8 karakter. Saat edit, kosongkan bila tidak ingin mengubah password.'),
            Forms\Components\TextInput::make('password_confirmation')
                ->label('Konfirmasi Password')
                ->password()
                ->revealable()
                ->dehydrated(false)
                ->required(fn (string $operation, Forms\Get $get): bool => $operation === 'create' || filled($get('password')))
                ->helperText('Ulangi password yang sama.'),
            Forms\Components\Select::make('mataPelajarans')
                ->label('Mata pelajaran yang diampu')
                ->relationship('mataPelajarans', 'nama')
                ->multiple()
                ->preload()
                ->helperText('Guru hanya bisa mengelola Bank Soal & melihat Hasil Ujian pada mapel ini.'),
            Forms\Components\Toggle::make('aktif')
                ->label('Akun aktif')
                ->default(true)
                ->helperText('Nonaktif = tidak bisa login ke panel.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('email')->label('Email')->searchable(),
                Tables\Columns\TextColumn::make('mata_pelajarans_count')
                    ->counts('mataPelajarans')
                    ->label('Mapel diampu')
                    ->badge(),
                Tables\Columns\IconColumn::make('aktif')->label('Aktif')->boolean(),
                Tables\Columns\TextColumn::make('created_at')->label('Dibuat')->dateTime('d/m/Y H:i')->sortable()->toggleable(isToggledHiddenByDefault: true),
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
            'index' => Pages\ListGurus::route('/'),
            'create' => Pages\CreateGuru::route('/create'),
            'edit' => Pages\EditGuru::route('/{record}/edit'),
        ];
    }
}
