<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Daftar Ujian</h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            {{-- Daftar ujian real-time (auto-refresh tiap 10 detik) --}}
            <livewire:exam.exam-list />
        </div>
    </div>
</x-app-layout>
