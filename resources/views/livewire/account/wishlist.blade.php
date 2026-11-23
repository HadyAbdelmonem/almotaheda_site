<div class="mx-auto max-w-6xl px-4 py-8">
    @include('livewire.account.partials.nav')
    <h1 class="mt-6 text-2xl font-extrabold text-ink">{{ __('store.my_wishlist') }}</h1>
    @if($products->isEmpty())
        {{-- M73: empty-wishlist scene (E16). --}}
        <div class="mt-8 rounded-xl bg-surface p-8 shadow-sm">
            <x-electric.scene name="empty-wishlist">
                <p class="text-lg font-bold text-ink">{{ __('store.empty_wishlist') }}</p>
                <p class="text-muted">{{ __('store.empty_wishlist_hint') }}</p>
                <x-btn :href="route('home')" class="mt-2">{{ __('store.browse_products') }}</x-btn>
            </x-electric.scene>
        </div>
    @else
        <div class="mt-6 grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-4">
            @foreach($products as $product)
                <x-product-card :product="$product" />
            @endforeach
        </div>
    @endif
</div>
