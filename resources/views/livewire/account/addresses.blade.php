<div class="mx-auto max-w-3xl px-4 py-8">
    @include('livewire.account.partials.nav')
    <h1 class="mt-6 text-2xl font-extrabold text-ink">{{ __('store.my_addresses') }}</h1>

    <div class="mt-4 grid gap-3">
        @foreach($addresses as $address)
            <article class="rounded-xl bg-surface p-4 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="font-extrabold text-ink">{{ $address->label }}</span>
                    @if($address->is_default)<span class="rounded-full bg-success/10 px-2 py-0.5 text-xs font-bold text-success">{{ __('store.default_address') }}</span>@endif
                </div>
                <p class="mt-1 text-sm text-body">{{ $address->recipient_name }} — <span dir="ltr">{{ $address->phone }}</span></p>
                <p class="text-sm text-body">{{ $address->governorate }}، {{ $address->city }}، {{ $address->address_line }}</p>
                <div class="mt-2 flex flex-wrap gap-2">
                    <button wire:click="edit({{ $address->id }})" class="min-h-11 rounded-lg border border-wire px-3 font-bold text-ink">{{ __('store.edit') }}</button>
                    @unless($address->is_default)
                        <button wire:click="makeDefault({{ $address->id }})" class="min-h-11 rounded-lg border border-wire px-3 font-bold text-ink">{{ __('store.make_default') }}</button>
                    @endunless
                    <button wire:click="delete({{ $address->id }})" wire:confirm="متأكد من حذف العنوان؟" class="min-h-11 rounded-lg border border-danger px-3 font-bold text-danger">{{ __('store.delete') }}</button>
                </div>
            </article>
        @endforeach
    </div>

    <form wire:submit="save" class="mt-6 grid gap-4 rounded-xl bg-surface p-5 shadow-sm">
        <h2 class="font-extrabold text-ink">{{ $editingId ? __('store.edit') : __('store.add_address') }}</h2>
        <div class="grid gap-4 md:grid-cols-2">
            <div><label class="mb-1 block font-semibold text-ink" for="label">{{ __('store.address_label') }}</label>
                <input id="label" wire:model="label" class="min-h-11 w-full rounded-lg border border-wire px-3">@error('label') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror</div>
            <div><label class="mb-1 block font-semibold text-ink" for="recipient">{{ __('store.recipient_name') }}</label>
                <input id="recipient" wire:model="recipientName" class="min-h-11 w-full rounded-lg border border-wire px-3">@error('recipientName') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror</div>
            <div><label class="mb-1 block font-semibold text-ink" for="phone">رقم الموبايل</label>
                <input id="phone" wire:model="phone" class="min-h-11 w-full rounded-lg border border-wire px-3" dir="ltr">@error('phone') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror</div>
            <div><label class="mb-1 block font-semibold text-ink" for="gov">{{ __('store.governorate') }}</label>
                <select id="gov" wire:model="governorate" class="min-h-11 w-full rounded-lg border border-wire px-3">
                    <option value="">{{ __('store.choose_governorate') }}</option>
                    @foreach($governorates as $gov)<option value="{{ $gov }}">{{ $gov }}</option>@endforeach
                </select>@error('governorate') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror</div>
            <div><label class="mb-1 block font-semibold text-ink" for="city">{{ __('store.city') }}</label>
                <input id="city" wire:model="city" class="min-h-11 w-full rounded-lg border border-wire px-3">@error('city') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror</div>
            <div><label class="mb-1 block font-semibold text-ink" for="line">{{ __('store.address_detail') }}</label>
                <input id="line" wire:model="addressLine" class="min-h-11 w-full rounded-lg border border-wire px-3">@error('addressLine') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror</div>
        </div>
        <label class="flex items-center gap-2 text-sm font-semibold text-ink"><input type="checkbox" wire:model="isDefault"> {{ __('store.default_address') }}</label>
        <x-btn type="submit" class="w-fit">{{ __('store.save') }}</x-btn>
    </form>
</div>
