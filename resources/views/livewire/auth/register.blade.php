<div class="mx-auto max-w-md px-4 py-10">
    <h1 class="text-2xl font-extrabold text-ink">{{ __('store.register') }}</h1>
    <form wire:submit="register" class="mt-6 grid gap-4 rounded-xl bg-surface p-5 shadow-sm">
        <div>
            <label for="name" class="mb-1 block font-semibold text-ink">{{ __('store.full_name') }}</label>
            <input id="name" type="text" wire:model="name" class="min-h-11 w-full rounded-lg border border-wire bg-white px-3" autocomplete="name">
            @error('name') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="identifier" class="mb-1 block font-semibold text-ink">{{ __('store.identifier') }}</label>
            <input id="identifier" type="text" wire:model="identifier" class="min-h-11 w-full rounded-lg border border-wire bg-white px-3" autocomplete="username">
            @error('identifier') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="password" class="mb-1 block font-semibold text-ink">{{ __('store.password') }}</label>
            <input id="password" type="password" wire:model="password" class="min-h-11 w-full rounded-lg border border-wire bg-white px-3" autocomplete="new-password">
            @error('password') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="password_confirmation" class="mb-1 block font-semibold text-ink">{{ __('store.password_confirmation') }}</label>
            <input id="password_confirmation" type="password" wire:model="passwordConfirmation" class="min-h-11 w-full rounded-lg border border-wire bg-white px-3" autocomplete="new-password">
            @error('passwordConfirmation') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
        </div>
        <x-btn type="submit" class="w-full">{{ __('store.register') }}</x-btn>
        <p class="text-center text-sm text-muted">{{ __('store.have_account') }}
            <a href="{{ route('login') }}" wire:navigate class="font-bold text-link">{{ __('store.login') }}</a>
        </p>
    </form>
</div>
