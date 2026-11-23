@php
    $navCategories = \App\Support\StorefrontNav::categories();
    $topbarMessages = \App\Support\StorefrontNav::topbarMessages();
    $storeName = trim((string) setting('store_name', '')) ?: __('store.name');
    $phones = setting('phones', []);
    $phones = is_array($phones) ? array_values(array_filter(array_map(fn ($p) => trim((string) $p), $phones))) : [];
@endphp
{{-- Global header (spec §6.5/§10.5, M01/M10-M13/M16): moving topbar,
     sticky constant-height bar, dark desktop nav strip + mega menu,
     mobile drawer. All identity data comes from settings. --}}
<div x-data="{ mobileSearch: false, megaOpen: false, megaTimer: null }">

    {{-- M11 topbar marquee: pauses on hover/focus/touch; static in lite/off. --}}
    @if($topbarMessages !== [])
        <div class="topbar bg-ink text-white" x-live>
            <div class="topbar-marquee ambient overflow-hidden py-2" tabindex="0" aria-label="{{ $topbarMessages[0] }}">
                <div class="marquee-track flex w-max items-center gap-8 whitespace-nowrap px-4">
                    @foreach([0, 1] as $copy)
                        <div class="flex items-center gap-8" @if($copy === 1) aria-hidden="true" @endif>
                            @foreach($topbarMessages as $message)
                                <span class="flex items-center gap-8 text-sm font-semibold">
                                    {{ $message }}
                                    <x-icons.zap :size="14" class="text-primary" />
                                </span>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    {{-- Sticky main bar: constant height 64px mobile / 72px desktop (M10). --}}
    <div data-header class="sticky top-0 z-40 bg-surface">
        <div class="header-scroll-overlay pointer-events-none absolute inset-0 border-b border-wire-dark/25 opacity-0 transition-opacity duration-200" aria-hidden="true"></div>
        <div class="relative mx-auto flex h-16 max-w-7xl items-center gap-2 px-4 md:h-[72px] md:gap-4 md:px-6">
            {{-- Mobile hamburger: opens the native dialog drawer (M13). --}}
            <button type="button" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg text-ink lg:hidden"
                    onclick="document.getElementById('mobile-drawer')?.showModal()" aria-label="{{ __('store.menu') }}">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
            </button>

            <a href="{{ url('/') }}" wire:navigate class="flex shrink-0 items-center gap-2" aria-label="{{ $storeName }}">
                <img src="{{ store_logo_url() }}" alt="{{ $storeName }}" width="36" height="36" class="h-9 w-9 shrink-0 rounded-lg object-contain">
                <span class="text-lg font-extrabold text-ink md:text-xl">{{ $storeName }}</span>
            </a>

            {{-- Desktop live search (single LiveSearch instance; on mobile it
                 becomes the full-width panel toggled by the search icon). --}}
            <div class="min-w-0 flex-1 max-md:absolute max-md:inset-x-0 max-md:top-full max-md:z-40 max-md:bg-surface max-md:p-3 max-md:shadow-md"
                 x-bind:class="mobileSearch ? 'max-md:block' : 'max-md:hidden'">
                <livewire:live-search />
            </div>

            <div class="ms-auto flex items-center gap-1 md:gap-2">
                @if($phones !== [])
                    <a href="tel:{{ preg_replace('/\D+/', '', $phones[0]) }}" class="hidden min-h-11 items-center gap-2 rounded-lg px-2 font-bold text-ink xl:flex" dir="ltr">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.13.96.36 1.9.7 2.8a2 2 0 0 1-.45 2.1L8.1 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.45c.9.34 1.84.57 2.8.7A2 2 0 0 1 22 16.9z"/></svg>
                        <span class="tabular-nums">{{ $phones[0] }}</span>
                    </a>
                @endif
                <button type="button" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg text-ink md:hidden"
                        x-on:click="mobileSearch = !mobileSearch" aria-label="{{ __('store.search') }}">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
                </button>
                <a href="{{ auth()->check() ? route('account.orders') : route('login') }}" wire:navigate class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg text-ink" aria-label="{{ __('store.account') }}">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 5-5.5 8-5.5s6.5 1.5 8 5.5"/></svg>
                </a>
                <livewire:cart-count />
            </div>
        </div>
    </div>

    {{-- Desktop dark nav strip + mega menu (M12). --}}
    <nav class="relative z-30 hidden bg-ink lg:block" aria-label="{{ __('store.all_categories') }}">
        <div class="mx-auto flex max-w-7xl items-stretch px-6">
            <button type="button"
                    class="flex min-h-11 items-center gap-2 bg-primary px-4 font-extrabold text-ink"
                    x-on:mouseenter="clearTimeout(megaTimer); megaTimer = setTimeout(() => megaOpen = true, 120)"
                    x-on:mouseleave="clearTimeout(megaTimer); megaTimer = setTimeout(() => megaOpen = false, 120)"
                    x-on:click="megaOpen = !megaOpen" x-bind:aria-expanded="megaOpen.toString()">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                {{ __('store.all_categories') }}
            </button>
            @foreach($navCategories as $category)
                <a href="{{ route('categories.show', $category->slug) }}" wire:navigate
                   class="flex min-h-11 items-center gap-2 px-3 text-sm font-semibold text-white hover:text-primary">
                    @if($category->effectiveIcon())
                        <x-electric.tool-icon :name="$category->effectiveIcon()" animate="none" :size="18" class="text-primary" />
                    @endif
                    {{ $category->name }}
                </a>
            @endforeach
        </div>
        {{-- Mega menu panel: fade + 8px slide, 150ms. --}}
        <div x-show="megaOpen" x-cloak
             x-transition:enter="motion-any:transition motion-any:duration-150"
             x-transition:enter-start="opacity-0 translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-on:mouseenter="clearTimeout(megaTimer); megaOpen = true"
             x-on:mouseleave="megaTimer = setTimeout(() => megaOpen = false, 120)"
             x-on:keydown.escape="megaOpen = false"
             class="absolute inset-x-0 top-full bg-surface shadow-md">
            <div class="mx-auto grid max-w-7xl grid-cols-3 gap-6 px-6 py-6 xl:grid-cols-4">
                @foreach($navCategories as $category)
                    <div>
                        <a href="{{ route('categories.show', $category->slug) }}" wire:navigate class="flex items-center gap-2 font-extrabold text-ink hover:text-link">
                            @if($category->effectiveIcon())
                                <x-electric.tool-icon :name="$category->effectiveIcon()" animate="hover" :size="22" class="text-primary-600" />
                            @endif
                            {{ $category->name }}
                        </a>
                        @if($category->children->isNotEmpty())
                            <ul class="mt-2 space-y-1">
                                @foreach($category->children as $child)
                                    <li><a href="{{ route('categories.show', $child->slug) }}" wire:navigate class="text-sm text-body hover:text-link hover:underline">{{ $child->name }}</a></li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </nav>

    {{-- Mobile drawer (M13): native <dialog> gives focus containment,
         Escape-to-close and focus restoration for free. Slides from the
         inline-start edge (right in RTL); accordions use <details>, so
         no height animation is involved. --}}
    <dialog id="mobile-drawer" class="mobile-drawer m-0 h-full max-h-none w-[85vw] max-w-xs bg-surface p-0 shadow-md backdrop:bg-ink/50 lg:hidden">
        <div class="flex items-center justify-between border-b border-wire px-4 py-3">
            <span class="flex items-center gap-2 font-extrabold text-ink">
                <img src="{{ store_logo_url() }}" alt="" width="28" height="28" class="h-7 w-7 shrink-0 rounded-md object-contain">
                {{ $storeName }}
            </span>
            <button type="button" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg text-ink"
                    onclick="document.getElementById('mobile-drawer')?.close()" aria-label="{{ __('store.close') }}">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
            </button>
        </div>
        <nav class="overflow-y-auto p-4" aria-label="{{ __('store.all_categories') }}">
            <ul class="space-y-1">
                @foreach($navCategories as $category)
                    <li>
                        @if($category->children->isNotEmpty())
                            <details class="group">
                                <summary class="flex min-h-11 cursor-pointer list-none items-center gap-2 rounded-lg px-2 font-bold text-ink">
                                    @if($category->effectiveIcon())
                                        <x-electric.tool-icon :name="$category->effectiveIcon()" animate="none" :size="20" class="text-primary-600" />
                                    @endif
                                    {{ $category->name }}
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="ms-auto transition-transform group-open:rotate-180"><path d="M6 9l6 6 6-6"/></svg>
                                </summary>
                                <ul class="mb-1 space-y-1 ps-9">
                                    <li><a href="{{ route('categories.show', $category->slug) }}" wire:navigate class="flex min-h-11 items-center text-sm font-semibold text-link">{{ __('store.view_all') }}</a></li>
                                    @foreach($category->children as $child)
                                        <li><a href="{{ route('categories.show', $child->slug) }}" wire:navigate class="flex min-h-11 items-center text-sm text-body">{{ $child->name }}</a></li>
                                    @endforeach
                                </ul>
                            </details>
                        @else
                            <a href="{{ route('categories.show', $category->slug) }}" wire:navigate class="flex min-h-11 items-center gap-2 rounded-lg px-2 font-bold text-ink">
                                @if($category->effectiveIcon())
                                    <x-electric.tool-icon :name="$category->effectiveIcon()" animate="none" :size="20" class="text-primary-600" />
                                @endif
                                {{ $category->name }}
                            </a>
                        @endif
                    </li>
                @endforeach
            </ul>
            <div class="mt-4 space-y-1 border-t border-wire pt-4">
                <a href="{{ url('/track-order') }}" wire:navigate class="flex min-h-11 items-center px-2 font-semibold text-body">{{ __('store.track_order') }}</a>
                <a href="{{ url('/contact') }}" wire:navigate class="flex min-h-11 items-center px-2 font-semibold text-body">{{ __('store.contact_us') }}</a>
                <a href="{{ url('/wholesale') }}" wire:navigate class="flex min-h-11 items-center px-2 font-semibold text-body">{{ __('store.wholesale_title') }}</a>
                @foreach($phones as $phone)
                    <a href="tel:{{ preg_replace('/\D+/', '', $phone) }}" class="flex min-h-11 items-center px-2 font-semibold text-body" dir="ltr"><span class="tabular-nums">{{ $phone }}</span></a>
                @endforeach
            </div>
        </nav>
    </dialog>
</div>
