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
 * Registration with one identifier field (phone OR email), spec §6.8.
 * At least one of phone/email is required — the identifier provides it.
 */
class RegisterPage extends Component
{
    public string $name = '';

    public string $identifier = '';

    public string $password = '';

    public string $passwordConfirmation = '';

    public function register(): mixed
    {
        $key = 'register:'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['identifier' => __('store.login_throttled')]);
        }
        RateLimiter::hit($key, 60);

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'identifier' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
            'passwordConfirmation' => ['required', 'same:password'],
        ]);

        $identifier = trim($this->identifier);
        $attrs = ['name' => $this->name, 'password' => $this->password];

        if (str_contains($identifier, '@')) {
            $email = strtolower($identifier);
            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || User::query()->where('email', $email)->exists()) {
                throw ValidationException::withMessages(['identifier' => __('store.identifier_taken')]);
            }
            $attrs['email'] = $email;
        } else {
            $phone = Phone::normalize($identifier);
            if (! Phone::isValidMobile($phone)) {
                throw ValidationException::withMessages(['identifier' => __('store.invalid_phone')]);
            }
            if (User::query()->where('phone', $phone)->exists()) {
                throw ValidationException::withMessages(['identifier' => __('store.identifier_taken')]);
            }
            $attrs['phone'] = $phone;
        }

        $user = User::create($attrs);
        Auth::login($user);
        session()->regenerate();

        return redirect()->route('account.orders');
    }

    public function render(): View
    {
        return view('livewire.auth.register')->layout('layouts.app', ['title' => __('store.register'), 'robots' => 'noindex,follow']);
    }
}
