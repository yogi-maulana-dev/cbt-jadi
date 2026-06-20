<?php

namespace App\Livewire\Forms;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Validate;
use Livewire\Form;

class LoginForm extends Form
{
    // Identitas login: No Ujian (siswa) ATAU email.
    #[Validate('required|string')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    #[Validate('boolean')]
    public bool $remember = false;

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $user = $this->resolveUser();

        if (! $user || ! Hash::check($this->password, $user->password)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'form.email' => trans('auth.failed'),
            ]);
        }

        // Akun dinonaktifkan admin.
        if (! $user->aktif) {
            throw ValidationException::withMessages([
                'form.email' => 'Akun Anda dinonaktifkan. Hubungi admin sekolah.',
            ]);
        }

        // Tolak siswa yang diblokir karena pelanggaran.
        if ($user->isBlocked()) {
            throw ValidationException::withMessages([
                'form.email' => 'Akun Anda diblokir karena pelanggaran saat ujian. Hubungi operator atau admin untuk membuka blokir.',
            ]);
        }

        Auth::login($user, $this->remember);

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Cari user dari identitas login: No Ujian (siswa) atau email.
     */
    public function resolveUser(): ?User
    {
        $id = trim($this->email);

        return User::where('email', $id)->orWhere('no_ujian', $id)->first();
    }

    /**
     * Ensure the authentication request is not rate limited.
     */
    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'form.email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the authentication rate limiting throttle key.
     */
    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->email).'|'.request()->ip());
    }
}
