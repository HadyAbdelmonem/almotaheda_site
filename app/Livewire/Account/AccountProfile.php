<?php

namespace App\Livewire\Account;

use App\Support\Phone;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/** Profile data + password change (spec §6.8, Phase 7). */
class AccountProfile extends Component
{
    public string $name = '';

    public string $phone = '';

    public string $email = '';

    public string $currentPassword = '';

    public string $newPassword = '';

    public string $newPasswordConfirmation = '';

    public function mount(): void
    {
        $user = Auth::user();
        $this->name = (string) $user->name;
        $this->phone = (string) ($user->phone ?? '');
        $this->email = (string) ($user->email ?? '');
    }

    public function save(): void
    {
        $user = Auth::user();
        $phone = $this->phone !== '' ? Phone::normalize($this->phone) : null;
        if ($phone !== null && ! Phone::isValidMobile($phone)) {
            throw ValidationException::withMessages(['phone' => __('store.invalid_phone')]);
        }

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ]);
        if ($phone !== null && $user->newQuery()->where('phone', $phone)->whereKeyNot($user->id)->exists()) {
            throw ValidationException::withMessages(['phone' => __('store.identifier_taken')]);
        }

        $user->update([
            'name' => $this->name,
            'phone' => $phone,
            'email' => $this->email !== '' ? strtolower($this->email) : null,
        ]);
        $this->dispatch('toast', message: __('store.saved_successfully'), type: 'success');
    }

    public function changePassword(): void
    {
        $this->validate([
            'currentPassword' => ['required'],
            'newPassword' => ['required', 'string', 'min:8'],
            'newPasswordConfirmation' => ['required', 'same:newPassword'],
        ]);
        $user = Auth::user();
        if (! Hash::check($this->currentPassword, $user->password)) {
            throw ValidationException::withMessages(['currentPassword' => __('store.wrong_current_password')]);
        }
        $user->update(['password' => $this->newPassword]); // hashed cast
        $this->reset(['currentPassword', 'newPassword', 'newPasswordConfirmation']);
        $this->dispatch('toast', message: __('store.saved_successfully'), type: 'success');
    }

    public function render(): View
    {
        return view('livewire.account.profile')->layout('layouts.app', ['title' => __('store.my_profile'), 'robots' => 'noindex,follow']);
    }
}
