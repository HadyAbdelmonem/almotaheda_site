{{-- Wholesale form (spec §6.9). Honeypot field is hidden from humans. --}}
<div>
    @if($sent)
        <div class="rounded-xl bg-success/10 px-4 py-6 text-center" role="status">
            <x-electric.bulb :on="true" :size="44" class="mx-auto text-success" />
            <p class="mt-3 font-bold text-ink">{{ __('store.wholesale_success') }}</p>
        </div>
    @else
        <form wire:submit="submit" class="space-y-4" novalidate>
            <div class="absolute -start-[9999px] h-0 w-0 overflow-hidden" aria-hidden="true">
                <label>Website <input type="text" wire:model="website" tabindex="-1" autocomplete="off"></label>
            </div>
            <div>
                <label for="ws-name" class="mb-1 block font-semibold text-ink">{{ __('store.full_name') }}</label>
                <input id="ws-name" type="text" wire:model="name" class="min-h-11 w-full rounded-lg border border-wire-dark/30 px-3 focus:outline-none focus:ring-2 focus:ring-primary">
                @error('name') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="ws-phone" class="mb-1 block font-semibold text-ink">{{ __('store.phone') }}</label>
                <input id="ws-phone" type="tel" wire:model="phone" class="min-h-11 w-full rounded-lg border border-wire-dark/30 px-3 focus:outline-none focus:ring-2 focus:ring-primary">
                @error('phone') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="ws-company" class="mb-1 block font-semibold text-ink">{{ __('store.company_optional') }}</label>
                <input id="ws-company" type="text" wire:model="company" class="min-h-11 w-full rounded-lg border border-wire-dark/30 px-3 focus:outline-none focus:ring-2 focus:ring-primary">
                @error('company') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="ws-volume" class="mb-1 block font-semibold text-ink">{{ __('store.monthly_volume_optional') }}</label>
                <input id="ws-volume" type="text" wire:model="monthlyVolume" class="min-h-11 w-full rounded-lg border border-wire-dark/30 px-3 focus:outline-none focus:ring-2 focus:ring-primary">
                @error('monthlyVolume') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="ws-message" class="mb-1 block font-semibold text-ink">{{ __('store.message_optional') }}</label>
                <textarea id="ws-message" wire:model="message" rows="4" class="w-full rounded-lg border border-wire-dark/30 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary"></textarea>
                @error('message') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
            </div>
            <x-btn type="submit" class="w-full sm:w-auto" wire:loading.attr="disabled">{{ __('store.send_request') }}</x-btn>
        </form>
    @endif
</div>
