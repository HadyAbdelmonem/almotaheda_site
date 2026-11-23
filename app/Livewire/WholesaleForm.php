<?php

namespace App\Livewire;

use App\Models\WholesaleRequest;
use App\Support\Phone;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

/**
 * Wholesale request form (spec §6.9): validation, honeypot, throttle;
 * requests land in wholesale_requests with status `new`.
 */
class WholesaleForm extends Component
{
    public string $name = '';

    public string $phone = '';

    public string $company = '';

    public string $monthlyVolume = '';

    public string $message = '';

    /** Honeypot: real users never fill this hidden field. */
    public string $website = '';

    public bool $sent = false;

    public function submit(): void
    {
        if ($this->website !== '') {
            $this->sent = true;

            return;
        }

        $key = 'wholesale-form:'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('message', __('store.form_throttled'));

            return;
        }
        RateLimiter::hit($key, 60);

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:32'],
            'company' => ['nullable', 'string', 'max:255'],
            'monthlyVolume' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:5000'],
        ]);

        if (! Phone::isValidMobile($this->phone)) {
            $this->addError('phone', __('store.invalid_phone'));

            return;
        }

        WholesaleRequest::create([
            'name' => $this->name,
            'phone' => Phone::normalize($this->phone),
            'company' => $this->company !== '' ? $this->company : null,
            'monthly_volume' => $this->monthlyVolume !== '' ? $this->monthlyVolume : null,
            'message' => $this->message !== '' ? $this->message : null,
            'status' => 'new',
        ]);

        $this->reset(['name', 'phone', 'company', 'monthlyVolume', 'message']);
        $this->sent = true;
    }

    public function render(): View
    {
        return view('livewire.wholesale-form');
    }
}
