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
            'nama_sekolah' => Setting::namaSekolah(),
            'tahun_pelajaran' => Setting::tahunPelajaran(),
            'kepala_sekolah' => Setting::kepalaSekolah(),
            'judul_kartu' => Setting::judulKartu(),
            'logo_sekolah' => Setting::logoSekolah(),
            'logo_bawah' => Setting::logoBawah(),
            'ttd_gambar' => Setting::ttdGambar(),
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
                Forms\Components\Section::make('Identitas Sekolah (untuk Kartu Ujian)')
                    ->description('Dipakai pada header & tanda tangan kartu ujian siswa yang dicetak.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('nama_sekolah')
                            ->label('Nama Sekolah')
                            ->maxLength(150),
                        Forms\Components\TextInput::make('tahun_pelajaran')
                            ->label('Tahun Pelajaran')
                            ->placeholder('2025/2026')
                            ->maxLength(20),
                        Forms\Components\TextInput::make('judul_kartu')
                            ->label('Judul Kartu')
                            ->placeholder('KARTU UJIAN')
                            ->maxLength(80),
                        Forms\Components\TextInput::make('kepala_sekolah')
                            ->label('Nama Kepala Sekolah (tanda tangan)')
                            ->maxLength(120),
                        Forms\Components\FileUpload::make('logo_sekolah')
                            ->label('Logo Atas (header kartu)')
                            ->image()
                            ->directory('sekolah')
                            ->maxSize(1024),
                        Forms\Components\FileUpload::make('logo_bawah')
                            ->label('Logo Bawah (footer kartu)')
                            ->image()
                            ->directory('sekolah')
                            ->maxSize(1024),
                        Forms\Components\FileUpload::make('ttd_gambar')
                            ->label('Gambar Tanda Tangan Kepala Sekolah')
                            ->image()
                            ->directory('sekolah')
                            ->maxSize(1024)
                            ->helperText('Tanda tangan (boleh + stempel) yang tampil di kartu ujian.')
                            ->columnSpanFull(),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        Setting::set('exam_kick_title', $data['exam_kick_title'] ?: Setting::DEFAULT_KICK_TITLE);
        Setting::set('exam_kick_message', $data['exam_kick_message'] ?: Setting::DEFAULT_KICK_MESSAGE);

        Setting::set('nama_sekolah', $data['nama_sekolah'] ?? null);
        Setting::set('tahun_pelajaran', $data['tahun_pelajaran'] ?? null);
        Setting::set('judul_kartu', $data['judul_kartu'] ?? null);
        Setting::set('kepala_sekolah', $data['kepala_sekolah'] ?? null);
        Setting::set('logo_sekolah', $data['logo_sekolah'] ?? null);
        Setting::set('logo_bawah', $data['logo_bawah'] ?? null);
        Setting::set('ttd_gambar', $data['ttd_gambar'] ?? null);

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
