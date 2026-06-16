<x-filament-panels::page
    @class([
        'fi-resource-list-records-page',
        'fi-resource-' . str_replace('/', '-', $this->getResource()::getSlug()),
    ])
>
    <div class="flex flex-col gap-y-6">
        @if (filled($this->test))
            {{ $this->table }}
        @else
            <div class="rounded-xl border border-dashed border-gray-300 bg-white p-10 text-center dark:border-gray-700 dark:bg-gray-900">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Klik tombol <strong>“Lihat Hasil”</strong> pada salah satu kartu ujian di atas
                    untuk menampilkan peserta &amp; hasilnya.
                </p>
            </div>
        @endif
    </div>
</x-filament-panels::page>
