<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Pengguna';

    protected static ?string $modelLabel = 'Pengguna';

    protected static ?string $navigationGroup = 'Pengaturan';

    /** Hanya admin/superadmin yang boleh mengelola pengguna. */
    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Nama')
                ->required()
                ->maxLength(120),
            Forms\Components\TextInput::make('email')
                ->email()
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(150),
            Forms\Components\Select::make('role')
                ->label('Peran (role)')
                ->options([
                    'siswa' => 'Siswa',
                    'guru' => 'Guru (input soal & lihat hasil)',
                    'pengawas' => 'Pengawas (token & awasi ruangan)',
                    'operator' => 'Operator (buka blokir & lihat jadwal)',
                    'admin' => 'Admin',
                    'superadmin' => 'Super Admin',
                ])
                ->default('siswa')
                ->required()
                ->live(),
            Forms\Components\TextInput::make('password')
                ->label('Password')
                ->password()
                ->revealable()
                ->maxLength(255)
                ->required(fn (string $operation): bool => $operation === 'create')
                ->dehydrated(fn (?string $state): bool => filled($state)) // saat edit, kosong = tidak diubah
                ->helperText('Saat edit, kosongkan bila tidak ingin mengubah password.'),
            Forms\Components\Select::make('mataPelajarans')
                ->label('Mata pelajaran yang diampu')
                ->relationship('mataPelajarans', 'nama')
                ->multiple()
                ->preload()
                ->visible(fn (Forms\Get $get): bool => $get('role') === 'guru')
                ->helperText('Khusus Guru: hanya bisa mengelola Bank Soal & melihat Hasil Ujian pada mapel ini.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('email')->searchable(),
                Tables\Columns\TextColumn::make('role')
                    ->label('Peran')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'guru' => 'Guru',
                        'pengawas' => 'Pengawas',
                        'operator' => 'Operator',
                        'superadmin' => 'Super Admin',
                        default => ucfirst($state),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'superadmin' => 'danger',
                        'admin' => 'warning',
                        'operator' => 'info',
                        'guru' => 'success',
                        'pengawas' => 'primary',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('mata_pelajarans_count')
                    ->counts('mataPelajarans')
                    ->label('Mapel diampu')
                    ->badge(),
                Tables\Columns\IconColumn::make('diblokir')
                    ->label('Diblokir')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')->options([
                    'siswa' => 'Siswa',
                    'operator' => 'Operator (Guru)',
                    'admin' => 'Admin',
                    'superadmin' => 'Super Admin',
                ]),
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
