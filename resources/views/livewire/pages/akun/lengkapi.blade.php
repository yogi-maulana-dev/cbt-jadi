<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function simpan(): void
    {
        $user = Auth::user();

        $data = $this->validate([
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['required', 'confirmed', 'min:6'],
        ]);

        $user->forceFill([
            'email' => $data['email'],
            'password' => $data['password'], // cast 'hashed' meng-hash otomatis
            'must_change_password' => false,
        ])->save();

        session()->flash('status', 'Akun berhasil dilengkapi. Selamat mengerjakan ujian.');
        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <div class="mb-4 rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-800">
        Login pertama: silakan <strong>ganti password</strong> dan masukkan <strong>email aktif</strong> Anda
        sebelum mengikuti ujian.
    </div>

    <form wire:submit="simpan">
        <div>
            <x-input-label for="email" :value="__('Email aktif')" />
            <x-text-input wire:model="email" id="email" type="email" class="block mt-1 w-full" required autofocus />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password" :value="__('Password baru')" />
            <x-text-input wire:model="password" id="password" type="password" class="block mt-1 w-full" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Ulangi password baru')" />
            <x-text-input wire:model="password_confirmation" id="password_confirmation" type="password" class="block mt-1 w-full" required autocomplete="new-password" />
        </div>

        <div class="flex items-center justify-end mt-6">
            <x-primary-button>{{ __('Simpan & Lanjut') }}</x-primary-button>
        </div>
    </form>
</div>
