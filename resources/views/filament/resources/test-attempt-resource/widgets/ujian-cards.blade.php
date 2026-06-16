<x-filament-widgets::widget>
    @php($active = $this->getActiveTest())

    <div class="space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-base font-semibold text-gray-900 dark:text-white">Pilih Ujian</h2>
            <input
                type="search"
                wire:model.live.debounce.300ms="search"
                placeholder="Cari ujian / mata pelajaran..."
                class="w-64 rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white" />
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @forelse ($this->getUjian() as $ujian)
                @php($isActive = $active === (string) $ujian->id)
                <div @class([
                    'flex flex-col rounded-xl border bg-white p-4 shadow-sm transition dark:bg-gray-900',
                    'border-primary-500 ring-2 ring-primary-500' => $isActive,
                    'border-gray-200 dark:border-gray-700 hover:shadow-md' => ! $isActive,
                ])>
                    <span class="text-base font-bold text-gray-900 dark:text-white">{{ $ujian->judul }}</span>
                    <span class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                        {{ optional($ujian->mataPelajaran)->nama ?? 'Tanpa mapel' }}
                        @if (optional($ujian->mataPelajaran)->kode)
                            ({{ $ujian->mataPelajaran->kode }})
                        @endif
                    </span>

                    <div class="mt-2 flex flex-wrap items-center gap-2 text-sm">
                        <span class="text-gray-600 dark:text-gray-300">{{ $ujian->attempts_count }} peserta</span>
                        @if ($ujian->sedang_count)
                            <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-400/10 dark:text-amber-400">
                                {{ $ujian->sedang_count }} sedang mengerjakan
                            </span>
                        @endif
                    </div>

                    <div class="mt-3 flex flex-wrap gap-2">
                        <x-filament::button
                            tag="a"
                            href="{{ $this->urlFor($ujian->id) }}"
                            size="sm"
                            :color="$isActive ? 'primary' : 'gray'"
                            icon="heroicon-m-eye">
                            {{ $isActive ? 'Sedang dilihat' : 'Lihat Hasil' }}
                        </x-filament::button>

                        <x-filament::button
                            tag="a"
                            href="{{ $this->liveUrlFor($ujian->id) }}"
                            size="sm"
                            color="danger"
                            icon="heroicon-m-signal">
                            Live{{ $ujian->sedang_count ? ' ('.$ujian->sedang_count.')' : '' }}
                        </x-filament::button>

                        <x-filament::button
                            tag="a"
                            href="{{ $this->riwayatUrlFor($ujian->id) }}"
                            size="sm"
                            color="warning"
                            icon="heroicon-m-shield-exclamation">
                            Riwayat
                        </x-filament::button>
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ $this->search !== '' ? 'Tidak ada ujian yang cocok.' : 'Belum ada ujian.' }}
                </p>
            @endforelse
        </div>
    </div>
</x-filament-widgets::widget>
