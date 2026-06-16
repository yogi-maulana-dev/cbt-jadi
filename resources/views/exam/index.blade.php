<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Daftar Ujian</h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            @if (session('exam_status'))
                <div class="mb-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    {{ session('exam_status') }}
                </div>
            @endif

            {{-- Daftar ujian real-time (auto-refresh tiap 10 detik) --}}
            <livewire:exam.exam-list />
        </div>
    </div>
</x-app-layout>
