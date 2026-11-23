<div class="mx-auto max-w-3xl px-4 py-8">
    @include('livewire.account.partials.nav')
    <h1 class="mt-6 text-2xl font-extrabold text-ink">{{ __('store.my_profile') }}</h1>
    <form wire:submit="save" class="mt-4 grid gap-4 rounded-xl bg-surface p-5 shadow-sm">
        <div>
            <label for="name" class="mb-1 block font-semibold text-ink">{{ __('store.full_name') }}</label>
            <input id="name" wire:model="name" class="min-h-11 w-full rounded-lg border border-wire px-3">
            @error('name') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="phone" class="mb-1 block font-semibold text-ink">{{ __('store.phone') ?? 'رقم الموبايل' }}</label>
            <input id="phone" wire:model="phone" class="min-h-11 w-full rounded-lg border border-wire px-3" dir="ltr">
            @error('phone') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="email" class="mb-1 block font-semibold text-ink">{{ __('store.email_optional') }}</label>
            <input id="email" type="email" wire:model="email" class="min-h-11 w-full rounded-lg border border-wire px-3" dir="ltr">
            @error('email') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
        </div>
        <x-btn type="submit" class="w-fit">{{ __('store.save') }}</x-btn>
    </form>

    <form wire:submit="changePassword" class="mt-6 grid gap-4 rounded-xl bg-surface p-5 shadow-sm">
        <h2 class="font-extrabold text-ink">{{ __('store.new_password') }}</h2>
        <div>
            <label for="current_password" class="mb-1 block font-semibold text-ink">{{ __('store.current_password') }}</label>
            <input id="current_password" type="password" wire:model="currentPassword" class="min-h-11 w-full rounded-lg border border-wire px-3">
            @error('currentPassword') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="new_password" class="mb-1 block font-semibold text-ink">{{ __('store.new_password') }}</label>
            <input id="new_password" type="password" wire:model="newPassword" class="min-h-11 w-full rounded-lg border border-wire px-3">
            @error('newPassword') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="new_password_confirmation" class="mb-1 block font-semibold text-ink">{{ __('store.password_confirmation') }}</label>
            <input id="new_password_confirmation" type="password" wire:model="newPasswordConfirmation" class="min-h-11 w-full rounded-lg border border-wire px-3">
            @error('newPasswordConfirmation') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
        </div>
        <x-btn type="submit" class="w-fit">{{ __('store.save') }}</x-btn>
    </form>
</div>
