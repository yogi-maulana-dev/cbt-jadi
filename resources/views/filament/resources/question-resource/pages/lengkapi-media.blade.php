<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Berikut soal dari import terakhir, sudah bernomor urut. Untuk soal yang butuh gambar atau video,
            cukup <strong>seret file ke kotak</strong> di soal yang sesuai (atau tempel URL video). Soal tanpa media biarkan kosong.
        </p>

        {{ $this->form }}

        <div class="flex justify-end gap-3">
            <x-filament::button
                tag="a"
                color="gray"
                href="{{ \App\Filament\Resources\QuestionResource::getUrl('index') }}">
                Lewati / Selesai
            </x-filament::button>

            <x-filament::button type="submit">
                Simpan Media
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
