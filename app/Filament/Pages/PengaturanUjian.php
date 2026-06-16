<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Pengaturan teks yang tampil ke siswa saat ujian dihentikan/di-reset.
 * Hanya admin yang boleh mengakses.
 */
class PengaturanUjian extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Pengaturan Ujian';

    protected static ?string $title = 'Pengaturan Ujian';

    protected static ?string $navigationGroup = 'Pengaturan';

    protected static string $view = 'filament.pages.pengaturan-ujian';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public function mount(): void
    {
        $this->form->fill([
            'exam_kick_title' => Setting::kickTitle(),
            'exam_kick_message' => Setting::kickMessage(),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Pesan saat ujian dihentikan / siswa dikeluarkan')
                    ->description('Teks ini muncul sebagai modal layar penuh di web dan di aplikasi Android ketika pengawas mengeluarkan/mereset peserta ujian.')
                    ->schema([
                        Forms\Components\TextInput::make('exam_kick_title')
                            ->label('Judul')
                            ->maxLength(120)
                            ->required()
                            ->placeholder(Setting::DEFAULT_KICK_TITLE),
                        Forms\Components\Textarea::make('exam_kick_message')
                            ->label('Isi pesan')
                            ->rows(4)
                            ->required()
                            ->placeholder(Setting::DEFAULT_KICK_MESSAGE),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        Setting::set('exam_kick_title', $data['exam_kick_title'] ?: Setting::DEFAULT_KICK_TITLE);
        Setting::set('exam_kick_message', $data['exam_kick_message'] ?: Setting::DEFAULT_KICK_MESSAGE);

        Notification::make()
            ->title('Pengaturan tersimpan')
            ->body('Pesan akan langsung dipakai untuk siswa yang dikeluarkan berikutnya.')
            ->success()
            ->send();
    }

    protected function getFormActions(): array
    {
        return [
            \Filament\Actions\Action::make('save')
                ->label('Simpan')
                ->submit('save'),
        ];
    }
}
