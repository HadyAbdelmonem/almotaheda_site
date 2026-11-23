<div class="mx-auto max-w-md px-4 py-10">
    <h1 class="text-2xl font-extrabold text-ink">{{ __('store.login') }}</h1>
    <form wire:submit="login" class="mt-6 grid gap-4 rounded-xl bg-surface p-5 shadow-sm">
        <div>
            <label for="identifier" class="mb-1 block font-semibold text-ink">{{ __('store.identifier') }}</label>
            <input id="identifier" type="text" wire:model="identifier" class="min-h-11 w-full rounded-lg border border-wire bg-white px-3" autocomplete="username">
            @error('identifier') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="password" class="mb-1 block font-semibold text-ink">{{ __('store.password') }}</label>
            <input id="password" type="password" wire:model="password" class="min-h-11 w-full rounded-lg border border-wire bg-white px-3" autocomplete="current-password">
            @error('password') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
        </div>
        <label class="flex items-center gap-2 text-sm text-body">
            <input type="checkbox" wire:model="remember"> {{ __('store.remember_me') }}
        </label>
        <x-btn type="submit" class="w-full">{{ __('store.login') }}</x-btn>
        <p class="text-center text-sm text-muted">{{ __('store.no_account_yet') }}
            <a href="{{ route('register') }}" wire:navigate class="font-bold text-link">{{ __('store.register') }}</a>
        </p>
    </form>
</div>
