<?php

use App\Livewire\Forms\LoginForm;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    public bool $blocked = false;

    public ?string $unblockedMessage = null;

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        // Kredensial benar tapi akun diblokir -> tampilkan layar tunggu (real-time).
        $user = $this->form->resolveUser();
        if ($user && $user->isBlocked() && Hash::check($this->form->password, $user->password)) {
            $this->blocked = true;
            $this->unblockedMessage = null;

            return;
        }

        $this->blocked = false;
        $this->form->authenticate();

        Session::regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }

    /**
     * Dipanggil berkala (wire:poll) selama layar "diblokir" tampil.
     * Begitu operator/admin membuka blokir, layar otomatis berubah.
     */
    public function pollBlocked(): void
    {
        if (! $this->blocked) {
            return;
        }

        $user = $this->form->resolveUser();

        if (! $user || ! $user->isBlocked()) {
            $this->blocked = false;
            $this->unblockedMessage = 'Akun Anda sudah dibuka. Silakan login kembali.';
        }
    }
}; ?>

<div>
    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    @if ($unblockedMessage)
        <div class="mb-4 rounded-md border border-green-300 bg-green-50 p-3 text-sm text-green-800">
            ✅ {{ $unblockedMessage }}
        </div>
    @endif

    @if ($blocked)
        {{-- Layar tunggu: memeriksa status blokir otomatis tiap 5 detik --}}
        <div wire:poll.5s="pollBlocked"
             class="rounded-md border border-red-300 bg-red-50 p-4 text-sm text-red-800">
            <p class="font-semibold">Akun Anda diblokir karena pelanggaran ujian.</p>
            <p class="mt-1">Menunggu operator/admin membuka blokir…</p>
            <div class="mt-3 flex items-center gap-2 text-xs text-red-600">
                <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                </svg>
                Memeriksa status secara otomatis…
            </div>
            <button type="button" wire:click="$set('blocked', false)"
                    class="mt-3 text-xs text-gray-500 underline">Kembali ke form login</button>
        </div>
    @else
        <form wire:submit="login">
            <!-- No Ujian / Email -->
            <div>
                <x-input-label for="email" :value="__('No Ujian / Email')" />
                <x-text-input wire:model="form.email" id="email" class="block mt-1 w-full" type="text" name="email" required autofocus autocomplete="username" placeholder="No Ujian atau email" />
                <x-input-error :messages="$errors->get('form.email')" class="mt-2" />
            </div>

            <!-- Password -->
            <div class="mt-4">
                <x-input-label for="password" :value="__('Password')" />

                <x-text-input wire:model="form.password" id="password" class="block mt-1 w-full"
                                type="password"
                                name="password"
                                required autocomplete="current-password" />

                <x-input-error :messages="$errors->get('form.password')" class="mt-2" />
            </div>

            <!-- Remember Me -->
            <div class="block mt-4">
                <label for="remember" class="inline-flex items-center">
                    <input wire:model="form.remember" id="remember" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" name="remember">
                    <span class="ms-2 text-sm text-gray-600">{{ __('Remember me') }}</span>
                </label>
            </div>

            <div class="flex items-center justify-end mt-4">
                @if (Route::has('password.request'))
                    <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('password.request') }}" wire:navigate>
                        {{ __('Forgot your password?') }}
                    </a>
                @endif

                <x-primary-button class="ms-3">
                    {{ __('Log in') }}
                </x-primary-button>
            </div>
        </form>
    @endif
</div>
