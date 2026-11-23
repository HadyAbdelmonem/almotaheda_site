<nav class="flex flex-wrap gap-2" aria-label="{{ __('store.account') }}">
    <a href="{{ route('account.orders') }}" wire:navigate class="min-h-11 rounded-lg bg-surface px-4 py-2 font-bold text-ink shadow-sm">{{ __('store.my_orders') }}</a>
    <a href="{{ route('account.addresses') }}" wire:navigate class="min-h-11 rounded-lg bg-surface px-4 py-2 font-bold text-ink shadow-sm">{{ __('store.my_addresses') }}</a>
    <a href="{{ route('account.wishlist') }}" wire:navigate class="min-h-11 rounded-lg bg-surface px-4 py-2 font-bold text-ink shadow-sm">{{ __('store.my_wishlist') }}</a>
    <a href="{{ route('account.profile') }}" wire:navigate class="min-h-11 rounded-lg bg-surface px-4 py-2 font-bold text-ink shadow-sm">{{ __('store.my_profile') }}</a>
    <form method="POST" action="{{ route('logout') }}" class="inline">
        @csrf
        <button type="submit" class="min-h-11 rounded-lg bg-ink px-4 py-2 font-bold text-white">{{ __('store.logout') }}</button>
    </form>
</nav>
