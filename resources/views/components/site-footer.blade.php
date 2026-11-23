@php
    $footerPages = \App\Support\StorefrontNav::pages();
    $storeName = trim((string) setting('store_name', '')) ?: __('store.name');
    $phones = setting('phones', []);
    $phones = is_array($phones) ? array_values(array_filter(array_map(fn ($p) => trim((string) $p), $phones))) : [];
    $email = trim((string) setting('email', ''));
    $address = trim((string) setting('address', ''));
    $social = setting('social_links', []);
    $socialLinks = [];
    if (is_array($social)) {
        foreach ($social as $key => $value) {
            if (is_array($value) && ! empty($value['url'])) {
                $socialLinks[(string) ($value['platform'] ?? $key)] = (string) $value['url'];
            } elseif (is_string($value) && trim($value) !== '') {
                $socialLinks[(string) $key] = trim($value);
            }
        }
    }
    $paymentMarks = [];
    if (setting('payment_cod_enabled', true)) { $paymentMarks[] = __('store.cod'); }
    if (setting('payment_bank_enabled', true)) { $paymentMarks[] = __('store.bank_transfer'); }
    if (setting('payment_card_enabled', false)) { $paymentMarks[] = __('store.card'); }
@endphp
{{-- Footer (spec §6.5/§10.6, M15): ink background, top wire with a slow
     pulse, back-to-top bolt button. All data comes from settings/pages. --}}
<div class="bg-ink text-white">
    <x-electric.wire orientation="h" :pulse="true" dark class="block" />
    <div class="mx-auto grid max-w-7xl gap-8 px-4 py-10 sm:grid-cols-2 md:px-6 lg:grid-cols-4">
        <div>
            <p class="flex items-center gap-2 text-lg font-extrabold">
                <img src="{{ store_logo_url() }}" alt="" width="32" height="32" class="h-8 w-8 shrink-0 rounded-lg object-contain"> {{ $storeName }}
            </p>
            <p class="mt-3 text-sm leading-7 text-white/70">{{ __('store.tagline') }}</p>
            @if($socialLinks !== [])
                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach($socialLinks as $platform => $url)
                        <a href="{{ $url }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center rounded-lg border border-white/25 px-3 text-sm font-semibold hover:border-primary hover:text-primary">{{ $platform }}</a>
                    @endforeach
                </div>
            @endif
        </div>
        <nav aria-label="{{ __('store.quick_links') }}">
            <h3 class="font-extrabold text-primary">{{ __('store.quick_links') }}</h3>
            <ul class="mt-3 space-y-1 text-sm">
                <li><a href="{{ url('/') }}" wire:navigate class="flex min-h-11 items-center text-white/85 hover:text-primary">{{ __('store.home') }}</a></li>
                <li><a href="{{ url('/track-order') }}" wire:navigate class="flex min-h-11 items-center text-white/85 hover:text-primary">{{ __('store.track_order') }}</a></li>
                <li><a href="{{ url('/contact') }}" wire:navigate class="flex min-h-11 items-center text-white/85 hover:text-primary">{{ __('store.contact_us') }}</a></li>
                <li><a href="{{ url('/wholesale') }}" wire:navigate class="flex min-h-11 items-center text-white/85 hover:text-primary">{{ __('store.wholesale_title') }}</a></li>
                <li><a href="{{ url('/cart') }}" wire:navigate class="flex min-h-11 items-center text-white/85 hover:text-primary">{{ __('store.cart') }}</a></li>
            </ul>
        </nav>
        @if($footerPages->isNotEmpty())
            <nav aria-label="{{ __('store.policies') }}">
                <h3 class="font-extrabold text-primary">{{ __('store.policies') }}</h3>
                <ul class="mt-3 space-y-1 text-sm">
                    @foreach($footerPages as $footerPage)
                        <li><a href="{{ url('/pages/'.$footerPage->slug) }}" wire:navigate class="flex min-h-11 items-center text-white/85 hover:text-primary">{{ $footerPage->title }}</a></li>
                    @endforeach
                </ul>
            </nav>
        @endif
        <div>
            <h3 class="font-extrabold text-primary">{{ __('store.contact_us') }}</h3>
            <ul class="mt-3 space-y-2 text-sm text-white/85">
                @foreach($phones as $phone)
                    <li><a href="tel:{{ preg_replace('/\D+/', '', $phone) }}" class="tabular-nums hover:text-primary" dir="ltr">{{ $phone }}</a></li>
                @endforeach
                @if($email !== '')
                    <li><a href="mailto:{{ $email }}" class="hover:text-primary">{{ $email }}</a></li>
                @endif
                @if($address !== '')
                    <li class="leading-7">{{ $address }}</li>
                @endif
            </ul>
            @if($paymentMarks !== [])
                <div class="mt-4 flex flex-wrap gap-2" aria-label="{{ __('store.payment_methods') }}">
                    @foreach($paymentMarks as $mark)
                        <span class="rounded-md bg-white/10 px-2.5 py-1.5 text-xs font-bold">{{ $mark }}</span>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
    <div class="border-t border-white/10">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 text-xs text-white/60 md:px-6">
            <span>© {{ now()->format('Y') }} {{ $storeName }}</span>
            {{-- M15 back-to-top bolt button. --}}
            <button type="button" onclick="window.scrollTo({ top: 0, behavior: 'smooth' })"
                    class="inline-flex min-h-11 items-center gap-2 rounded-lg px-2 font-bold text-white/85 hover:text-primary" aria-label="{{ __('store.back_to_top') }}">
                <x-icons.zap :size="16" class="text-primary" /> {{ __('store.back_to_top') }}
            </button>
        </div>
    </div>
</div>
