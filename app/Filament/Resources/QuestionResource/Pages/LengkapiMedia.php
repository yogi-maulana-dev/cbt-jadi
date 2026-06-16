<?php

namespace App\Filament\Resources\QuestionResource\Pages;

use App\Filament\Resources\QuestionResource;
use App\Models\Question;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Arr;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Halaman "Lengkapi Media": menampilkan soal hasil satu sesi import,
 * bernomor urut, lalu guru tinggal menyeret gambar / video ke soal yang cocok.
 */
class LengkapiMedia extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = QuestionResource::class;

    protected static string $view = 'filament.resources.question-resource.pages.lengkapi-media';

    protected static ?string $title = 'Lengkapi Gambar & Video Soal';

    protected static bool $shouldRegisterNavigation = false;

    public string $batch = '';

    public ?array $data = [];

    public function mount(string $batch): void
    {
        $this->batch = $batch;

        $items = Question::where('import_batch', $batch)
            ->orderBy('id')
            ->get(['id', 'pertanyaan', 'gambar', 'video_url', 'video_path', 'suara', 'media_pending'])
            ->values()
            ->map(fn (Question $q, int $i): array => [
                'id' => $q->id,
                'no' => $i + 1,
                'pertanyaan' => $q->pertanyaan,
                'gambar' => $q->gambar,
                'video_url' => $q->video_url,
                'video_path' => $q->video_path,
                'suara' => $q->suara,
                'media_pending' => $q->media_pending,
            ])->all();

        $this->form->fill(['soal' => $items]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Repeater::make('soal')
                    ->hiddenLabel()
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false)
                    ->itemLabel(function (array $state): string {
                        $hasMedia = filled($state['gambar'] ?? null) || filled($state['video_path'] ?? null)
                            || filled($state['video_url'] ?? null) || filled($state['suara'] ?? null);
                        $perluMedia = ($state['media_pending'] ?? false) && ! $hasMedia;

                        return ($perluMedia ? '⚠ BELUM ADA MEDIA — ' : '✓ ')
                            .'No. '.($state['no'] ?? '?').' — '.Str::limit((string) ($state['pertanyaan'] ?? ''), 60);
                    })
                    ->collapsible()
                    ->schema([
                        Forms\Components\Placeholder::make('teks')
                            ->hiddenLabel()
                            ->content(function (Forms\Get $get): HtmlString {
                                $hasMedia = filled($get('gambar')) || filled($get('video_path'))
                                    || filled($get('video_url')) || filled($get('suara'));
                                $perluMedia = $get('media_pending') && ! $hasMedia;
                                $no = e((string) $get('no'));
                                $teks = e((string) $get('pertanyaan'));

                                if ($perluMedia) {
                                    return new HtmlString(
                                        '<span style="color:#dc2626;font-weight:700">⚠ Soal No. '.$no.' — BELUM ADA GAMBAR/VIDEO</span><br>'.$teks
                                    );
                                }

                                return new HtmlString('<span style="font-weight:600">Soal No. '.$no.'</span>: '.$teks);
                            })
                            ->columnSpanFull(),
                        Forms\Components\FileUpload::make('gambar')
                            ->label('Gambar soal — seret ke sini')
                            ->image()
                            ->directory('soal')
                            ->imageEditor()
                            ->openable()
                            ->downloadable()
                            ->maxSize(2048)
                            ->helperText('Tersimpan otomatis begitu selesai diunggah. Maks 2 MB.')
                            ->live()
                            ->afterStateUpdated(fn (Forms\Components\FileUpload $component, Forms\Get $get) => $this->persistUpload($get('id'), 'gambar', $component)),
                        Forms\Components\FileUpload::make('video_path')
                            ->label('Video soal — seret file ke sini')
                            ->directory('soal-video')
                            ->acceptedFileTypes(['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime', 'video/x-msvideo', 'video/x-matroska', 'video/3gpp'])
                            ->maxSize(51200)
                            ->helperText('Tersimpan otomatis. Maks 50 MB. Format mp4 paling disarankan agar bisa diputar di semua browser.')
                            ->live()
                            ->afterStateUpdated(fn (Forms\Components\FileUpload $component, Forms\Get $get) => $this->persistUpload($get('id'), 'video_path', $component)),
                        Forms\Components\TextInput::make('video_url')
                            ->label('atau tempel URL video (mis. YouTube)')
                            ->url()
                            ->prefixIcon('heroicon-o-video-camera')
                            ->placeholder('https://youtu.be/...')
                            ->helperText('Tersimpan otomatis. Isi salah satu saja: file video ATAU URL.')
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (?string $state, Forms\Get $get) => $this->persistRow($get('id'), ['video_url' => $state ?: null])),
                        Forms\Components\FileUpload::make('suara')
                            ->label('Suara / audio — seret file ke sini')
                            ->directory('soal-audio')
                            ->acceptedFileTypes(['audio/mpeg', 'audio/mp3', 'audio/ogg', 'audio/wav', 'audio/x-wav', 'audio/aac', 'audio/mp4', 'audio/x-m4a'])
                            ->maxSize(20480)
                            ->helperText('Tersimpan otomatis. mp3/ogg/wav, maks 20 MB (untuk soal listening).')
                            ->columnSpanFull()
                            ->live()
                            ->afterStateUpdated(fn (Forms\Components\FileUpload $component, Forms\Get $get) => $this->persistUpload($get('id'), 'suara', $component)),
                        Forms\Components\Hidden::make('id'),
                        Forms\Components\Hidden::make('no'),
                        Forms\Components\Hidden::make('pertanyaan'),
                        Forms\Components\Hidden::make('media_pending'),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    /**
     * Simpan otomatis sebuah file (gambar/video) ke soal SEKETIKA setelah diunggah,
     * sehingga tidak hilang walau browser ditutup sebelum tombol "Simpan".
     */
    protected function persistUpload(?int $id, string $column, Forms\Components\FileUpload $component): void
    {
        $component->saveUploadedFiles();
        $paths = Arr::wrap($component->getState());
        $path = $paths ? (string) reset($paths) : null;

        $this->persistRow($id, [$column => $path ?: null]);
    }

    /**
     * Tulis perubahan media ke satu soal; bila kini ada media, penanda "perlu media" otomatis dilepas.
     *
     * @param  array<string, mixed>  $attrs
     */
    protected function persistRow(?int $id, array $attrs): void
    {
        if (! $id) {
            return;
        }

        $q = Question::find($id);
        if (! $q) {
            return;
        }

        $q->fill($attrs);
        if ($q->gambar || $q->video_path || $q->video_url || $q->suara) {
            $q->media_pending = false;
        }
        $q->save();
    }

    public function save(): void
    {
        $items = $this->form->getState()['soal'] ?? [];

        $count = 0;
        foreach ($items as $item) {
            $this->persistRow($item['id'] ?? null, [
                'gambar' => $item['gambar'] ?: null,
                'video_path' => $item['video_path'] ?: null,
                'video_url' => $item['video_url'] ?: null,
                'suara' => $item['suara'] ?: null,
            ]);
            $count++;
        }

        Notification::make()
            ->title('Media tersimpan')
            ->body("Media {$count} soal sudah tersimpan. (Setiap unggahan juga sudah otomatis tersimpan.)")
            ->success()
            ->send();
    }

    public function getBreadcrumb(): string
    {
        return 'Lengkapi Media';
    }
}
