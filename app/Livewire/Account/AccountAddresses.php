<?php

namespace App\Livewire\Account;

use App\Models\Address;
use App\Models\ShippingRate;
use App\Support\Phone;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Saved addresses (spec §6.8). At most ONE default per user, enforced
 * inside a transaction: setting a default unsets the previous one.
 * All queries are scoped to the authenticated user.
 */
class AccountAddresses extends Component
{
    public ?int $editingId = null;

    public string $label = '';

    public string $recipientName = '';

    public string $phone = '';

    public string $governorate = '';

    public string $city = '';

    public string $addressLine = '';

    public bool $isDefault = false;

    public function edit(int $id): void
    {
        $address = $this->own($id);
        $this->editingId = $address->id;
        $this->label = (string) $address->label;
        $this->recipientName = (string) $address->recipient_name;
        $this->phone = (string) $address->phone;
        $this->governorate = (string) $address->governorate;
        $this->city = (string) $address->city;
        $this->addressLine = (string) $address->address_line;
        $this->isDefault = (bool) $address->is_default;
    }

    public function save(): void
    {
        $phone = Phone::normalize($this->phone);
        if (! Phone::isValidMobile($phone)) {
            throw ValidationException::withMessages(['phone' => __('store.invalid_phone')]);
        }
        $this->validate([
            'label' => ['required', 'string', 'max:100'],
            'recipientName' => ['required', 'string', 'max:255'],
            'governorate' => ['required', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100'],
            'addressLine' => ['required', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($phone) {
            $user = Auth::user();
            $data = [
                'label' => $this->label, 'recipient_name' => $this->recipientName, 'phone' => $phone,
                'governorate' => $this->governorate, 'city' => $this->city, 'address_line' => $this->addressLine,
                'is_default' => $this->isDefault,
            ];
            $address = $this->editingId !== null
                ? tap($this->own($this->editingId))->update($data)
                : $user->addresses()->create($data);

            if ($this->isDefault) {
                $user->addresses()->whereKeyNot($address->id)->update(['is_default' => false]);
            }
        });

        $this->reset(['editingId', 'label', 'recipientName', 'phone', 'governorate', 'city', 'addressLine', 'isDefault']);
        $this->dispatch('toast', message: __('store.saved_successfully'), type: 'success');
    }

    public function makeDefault(int $id): void
    {
        DB::transaction(function () use ($id) {
            $address = $this->own($id);
            Auth::user()->addresses()->whereKeyNot($address->id)->update(['is_default' => false]);
            $address->update(['is_default' => true]);
        });
    }

    public function delete(int $id): void
    {
        $this->own($id)->delete();
    }

    private function own(int $id): Address
    {
        return Auth::user()->addresses()->findOrFail($id);
    }

    public function render(): View
    {
        return view('livewire.account.addresses', [
            'addresses' => Auth::user()->addresses()->orderByDesc('is_default')->latest()->get(),
            'governorates' => ShippingRate::query()->where('is_active', true)->orderBy('sort_order')->pluck('name_ar'),
        ])->layout('layouts.app', ['title' => __('store.my_addresses'), 'robots' => 'noindex,follow']);
    }
}
