@extends('layouts.app', ['title' => __('store.error_404_title'), 'robots' => 'noindex,follow'])

@section('content')
    {{-- Custom Arabic 404 (spec §6.10, M70). Real content first: the
         scene is a bonus; search and links work with JS disabled. --}}
    <section class="mx-auto max-w-3xl px-4 py-12">
        <div class="rounded-xl bg-surface px-5 py-10 shadow-sm">
            <x-electric.scene name="404">
                <h1 class="text-2xl font-extrabold text-ink">{{ __('store.error_404_title') }}</h1>
                <p class="max-w-md text-muted">{{ __('store.error_404_text') }}</p>
            </x-electric.scene>

            <form method="GET" action="{{ route('search') }}" class="mx-auto mt-8 flex max-w-md gap-2">
                <label class="sr-only" for="error-search-q">{{ __('store.search') }}</label>
                <input id="error-search-q" type="search" name="q" placeholder="{{ __('store.search_placeholder') }}"
                       class="min-h-11 flex-1 rounded-lg border border-wire-dark/30 bg-white px-3">
                <x-btn type="submit">{{ __('store.search') }}</x-btn>
            </form>

            @php $errorCategories = \App\Support\StorefrontNav::categories(); @endphp
            @if($errorCategories->isNotEmpty())
                <nav class="mt-8 flex flex-wrap justify-center gap-2" aria-label="{{ __('store.all_categories') }}">
                    @foreach($errorCategories as $category)
                        <a href="{{ route('categories.show', $category->slug) }}" wire:navigate
                           class="inline-flex min-h-11 items-center rounded-full border border-wire-dark/30 px-4 text-sm font-semibold text-ink">{{ $category->name }}</a>
                    @endforeach
                </nav>
            @endif

            <div class="mt-8 text-center">
                <x-btn :href="url('/')">{{ __('store.back_to_home') }}</x-btn>
            </div>
        </div>
    </section>
@endsection
