<?php

namespace App\Livewire;

use App\Models\ContactMessage;
use App\Support\Phone;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

/**
 * Contact form (spec §6.9): validation, hand-written honeypot and
 * rate limiting; messages land in contact_messages for the admin.
 */
class ContactForm extends Component
{
    public string $name = '';

    public string $phone = '';

    public string $email = '';

    public string $message = '';

    /** Honeypot: real users never fill this hidden field. */
    public string $website = '';

    public bool $sent = false;

    public function submit(): void
    {
        if ($this->website !== '') {
            // Bot caught by the honeypot: pretend success, store nothing.
            $this->sent = true;

            return;
        }

        $key = 'contact-form:'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('message', __('store.form_throttled'));

            return;
        }
        RateLimiter::hit($key, 60);

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        if (! Phone::isValidMobile($this->phone)) {
            $this->addError('phone', __('store.invalid_phone'));

            return;
        }

        ContactMessage::create([
            'name' => $this->name,
            'phone' => Phone::normalize($this->phone),
            'email' => $this->email !== '' ? $this->email : null,
            'message' => $this->message,
        ]);

        $this->reset(['name', 'phone', 'email', 'message']);
        $this->sent = true;
    }

    public function render(): View
    {
        return view('livewire.contact-form');
    }
}
