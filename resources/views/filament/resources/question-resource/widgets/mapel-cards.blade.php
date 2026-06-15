<x-filament-widgets::widget>
    @php($active = $this->getActiveMapel())

    <div class="space-y-3">
        <div class="flex items-center justify-between">
            <h2 class="text-base font-semibold text-gray-900 dark:text-white">Pilih Mata Pelajaran</h2>
            <a href="{{ $this->urlFor('all') }}"
               class="text-sm font-medium text-primary-600 hover:underline dark:text-primary-400">
                Lihat semua soal
            </a>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @forelse ($this->getMapels() as $mapel)
                @php($isActive = $active === (string) $mapel->id)
                <div @class([
                    'flex flex-col rounded-xl border bg-white p-4 shadow-sm transition dark:bg-gray-900',
                    'border-primary-500 ring-2 ring-primary-500' => $isActive,
                    'border-gray-200 dark:border-gray-700 hover:shadow-md' => ! $isActive,
                ])>
                    <div class="flex items-start justify-between gap-2">
                        <span class="text-base font-bold text-gray-900 dark:text-white">{{ $mapel->nama }}</span>
                        <span class="shrink-0 rounded bg-gray-100 px-2 py-0.5 font-mono text-xs font-semibold text-gray-600 dark:bg-gray-700 dark:text-gray-200">
                            {{ $mapel->kode ?? '-' }}
                        </span>
                    </div>

                    <div class="mt-2 flex flex-wrap items-center gap-2 text-sm">
                        <span class="text-gray-600 dark:text-gray-300">{{ $mapel->questions_count }} soal</span>
                        @if ($mapel->media_pending_count)
                            <span class="inline-flex items-center gap-1 rounded-full bg-danger-50 px-2 py-0.5 text-xs font-medium text-danger-600 dark:bg-danger-400/10 dark:text-danger-400">
                                ⚠ {{ $mapel->media_pending_count }} perlu media
                            </span>
                        @endif
                    </div>

                    <div class="mt-3">
                        <x-filament::button
                            tag="a"
                            href="{{ $this->urlFor($mapel->id) }}"
                            size="sm"
                            :color="$isActive ? 'primary' : 'gray'"
                            icon="heroicon-m-eye">
                            {{ $isActive ? 'Sedang dilihat' : 'Lihat Soal' }}
                        </x-filament::button>
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-500 dark:text-gray-400">Belum ada mata pelajaran.</p>
            @endforelse
        </div>
    </div>
</x-filament-widgets::widget>
