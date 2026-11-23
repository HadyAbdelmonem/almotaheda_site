@extends('layouts.app')

@section('content')
    {{-- Homepage (spec §6.5/§10.6). Sections appear in the fixed order
         and hide automatically when their data is empty. --}}

    {{-- M20 hero slider: first slide eager + fetchpriority high, never
         revealed; autoplay (10s) only at full motion with a pause/play
         control. On mobile the controls sit at the top corner so they
         never overlap the slide caption. --}}
    @if($heroBanners->isNotEmpty())
        <section data-hero class="bg-ink" wire:ignore>
            <div class="relative mx-auto max-w-7xl">
                {{-- M21 (Phase 9B): decorative circuit overlay, desktop
                     only, fades in after window load (home.js), purely
                     decorative and hidden from assistive tech. --}}
                <x-electric.circuit variant="banner" :pulses="2" data-hero-ambient
                                    class="pointer-events-none absolute inset-0 z-[5] hidden opacity-0 transition-opacity duration-700 md:block" />
                <div class="swiper" data-hero-main dir="rtl">
                    <div class="swiper-wrapper">
                        @foreach($heroBanners as $i => $banner)
                            <div class="swiper-slide">
                                <div class="relative flex aspect-[4/3] items-end overflow-hidden md:aspect-[16/6]">
                                    @if($banner->image_desktop)
                                        <img src="{{ \App\Models\Banner::imageUrl($banner->image_desktop) }}" alt="{{ $banner->title }}"
                                             width="1600" height="600"
                                             @if($i === 0) loading="eager" fetchpriority="high" @else loading="lazy" @endif
                                             class="absolute inset-0 h-full w-full object-cover">
                                    @else
                                        <x-placeholder-image :name="$banner->title" ratio="wide" class="absolute inset-0 h-full w-full" />
                                    @endif
                                    <div class="relative w-full bg-ink/70 p-5 md:p-8">
                                        <h2 class="text-2xl font-extrabold text-white md:text-4xl">{{ $banner->title }}</h2>
                                        @if($banner->link_url)
                                            <x-btn :href="$banner->link_url" class="mt-4">{{ __('store.view_all') }}</x-btn>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="absolute end-4 top-3 z-10 flex items-center gap-3 md:bottom-4 md:top-auto">
                    <div data-hero-pagination class="swiper-pagination !static flex gap-1.5"></div>
                    <button type="button" data-hero-toggle data-state="paused"
                            data-label-play="{{ __('store.play_slides') }}" data-label-pause="{{ __('store.pause_slides') }}"
                            aria-label="{{ __('store.pause_slides') }}"
                            class="flex h-11 w-11 items-center justify-center rounded-full bg-white/15 text-sm text-white">
                        <span data-hero-toggle-icon aria-hidden="true">❚❚</span>
                    </button>
                </div>
            </div>
        </section>
    @endif

    <div class="mx-auto max-w-7xl space-y-12 px-4 py-10 md:px-6">
        {{-- Promo tiles (banners position=promo): 3 columns desktop,
             horizontal scroll-snap on mobile (M22). --}}
        @if($promoBanners->isNotEmpty())
            <section aria-label="{{ __('store.promo') }}">
                <div class="flex snap-x snap-mandatory gap-3 overflow-x-auto pb-2 md:grid md:grid-cols-3 md:overflow-visible" x-reveal.stagger>
                    @foreach($promoBanners as $banner)
                        <a @if($banner->link_url) href="{{ $banner->link_url }}" wire:navigate @endif
                           class="group relative block w-[78%] shrink-0 snap-start overflow-hidden rounded-xl bg-ink p-5 text-white motion-any:transition-transform motion-any:duration-200 hover:-translate-y-1 md:w-auto">
                            <x-electric.circuit variant="banner" :pulses="1" class="absolute inset-0 opacity-60" />
                            <span class="relative flex items-center gap-2 text-lg font-extrabold">
                                <x-icons.zap :size="20" class="text-primary" /> {{ $banner->title }}
                            </span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Category grid (M23): 3x3 on mobile, tool icons E12. --}}
        @if($categories->isNotEmpty())
            <section aria-label="{{ __('store.all_categories') }}">
                <x-section-title :title="__('store.shop_by_category')" />
                <div class="grid grid-cols-3 gap-3 sm:grid-cols-4 lg:grid-cols-6" x-reveal.stagger>
                    @foreach($categories as $category)
                        <a href="{{ route('categories.show', $category->slug) }}" wire:navigate
                           class="group flex flex-col items-center gap-2 rounded-xl bg-surface p-4 text-center shadow-sm motion-any:transition-transform motion-any:duration-200 hover:-translate-y-1">
                            @if($category->effectiveIcon())
                                <x-electric.tool-icon :name="$category->effectiveIcon()" animate="hover" :size="44" class="text-primary-600" />
                            @endif
                            <span class="text-sm font-bold text-ink">{{ $category->name }}</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Brands rail (owner pass, 2026-11-23): Swiper with 5s autoplay
             at full motion only, looping when there are enough brands;
             manual swipe always works and autoplay resumes afterwards.
             M25 (Phase 9B): logos sit on a thin wire rail with a single
             pulse while visible (E01, ambient + x-live). --}}
        @if($brands->isNotEmpty())
            <section aria-label="{{ __('store.brands') }}">
                <x-section-title :title="__('store.brands')" />
                <x-electric.wire orientation="h" :pulse="true" class="mb-1 opacity-70" />
                <div class="swiper" data-brands-rail dir="rtl">
                    <div class="swiper-wrapper">
                        @foreach($brands as $brand)
                            <div class="swiper-slide">
                                <a href="{{ route('brands.show', $brand->slug) }}" wire:navigate
                                   class="flex min-h-16 w-36 items-center justify-center rounded-xl bg-surface px-4 shadow-sm">
                                    @php $logo = $brand->getFirstMedia('logo'); @endphp
                                    @if($logo)
                                        <img src="{{ $logo->getUrl() }}" alt="{{ $brand->name }}" width="120" height="48" loading="lazy" class="max-h-12 object-contain md:grayscale md:hover:grayscale-0">
                                    @else
                                        <span class="font-extrabold text-ink">{{ $brand->name }}</span>
                                    @endif
                                </a>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        {{-- Best sellers carousel (M26): ~2.2 cards visible on mobile. --}}
        @if($bestSellers->isNotEmpty())
            <section aria-label="{{ __('store.best_sellers') }}">
                <x-section-title :title="__('store.best_sellers')" />
                <div class="swiper" data-product-carousel dir="rtl">
                    <div class="swiper-wrapper">
                        @foreach($bestSellers as $product)
                            <div class="swiper-slide h-auto"><x-product-card :product="$product" class="h-full" /></div>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        {{-- New arrivals grid. --}}
        @if($newArrivals->isNotEmpty())
            <section aria-label="{{ __('store.new_arrivals') }}">
                <x-section-title :title="__('store.new_arrivals')" />
                <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-5" x-reveal.stagger>
                    @foreach($newArrivals->take(5) as $product)
                        <div><x-product-card :product="$product" /></div>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Why us (settings-driven blocks) + wholesale banner (M27/M28). --}}
        <x-trust-block />

        <section class="relative overflow-hidden rounded-xl bg-ink p-6 text-white md:p-10" aria-label="{{ __('store.wholesale_title') }}">
            <x-electric.circuit variant="banner" :pulses="3" class="absolute inset-0 opacity-70" />
            <div class="relative max-w-xl">
                <h2 class="text-2xl font-extrabold">{{ __('store.wholesale_banner_title') }}</h2>
                <p class="mt-2 leading-8 text-white/80">{{ __('store.wholesale_banner_text') }}</p>
                <div class="mt-5 flex flex-wrap gap-3">
                    <x-btn :href="url('/wholesale')">{{ __('store.send_request') }}</x-btn>
                    <x-whatsapp-button :message="__('store.whatsapp_wholesale_message')" :label="__('store.order_via_whatsapp')" />
                </div>
            </div>
        </section>
    </div>
@endsection
