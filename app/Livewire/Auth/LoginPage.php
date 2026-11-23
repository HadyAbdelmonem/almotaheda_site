<?php

namespace App\Livewire\Auth;

use App\Models\User;
use App\Support\Phone;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Login with ONE identifier field: Egyptian phone OR email (spec
 * §6.8, Phase 7). Rate-limited. No OTP / social login in v1.
 */
class LoginPage extends Component
{
    public string $identifier = '';

    public string $password = '';

    public bool $remember = false;

    public function login(): mixed
    {
        $this->validate([
            'identifier' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $key = 'login:'.strtolower(trim($this->identifier)).'|'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['identifier' => __('store.login_throttled')]);
        }

        $user = $this->findUser($this->identifier);
        if ($user === null || ! Auth::attempt(['id' => $user->id, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['identifier' => __('store.invalid_credentials')]);
        }

        RateLimiter::clear($key);
        session()->regenerate();

        return redirect()->intended(route('account.orders'));
    }

    private function findUser(string $identifier): ?User
    {
        $identifier = trim($identifier);
        if (str_contains($identifier, '@')) {
            return User::query()->where('email', strtolower($identifier))->first();
        }
        $phone = Phone::normalize($identifier);
        if (! Phone::isValidMobile($phone)) {
            return null;
        }

        return User::query()->where('phone', $phone)->first();
    }

    public function render(): View
    {
        return view('livewire.auth.login')->layout('layouts.app', ['title' => __('store.login'), 'robots' => 'noindex,follow']);
    }
}
