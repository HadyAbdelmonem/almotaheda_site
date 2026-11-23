@extends('layouts.app', ['title' => __('store.error_500_title'), 'robots' => 'noindex,nofollow'])

@section('content')
    {{-- Custom Arabic 500 (spec §6.10, M71). No technical details are
         ever shown. The breaker switch is a real button: flipping it
         ON reloads the page (listener below); without JS the home
         link below still works. --}}
    <section class="mx-auto max-w-3xl px-4 py-12">
        <div class="rounded-xl bg-surface px-5 py-10 shadow-sm"
             x-data x-on:switch:on.window="setTimeout(() => window.location.reload(), 400)">
            <x-electric.scene name="500">
                <h1 class="text-2xl font-extrabold text-ink">{{ __('store.error_500_title') }}</h1>
                <p class="max-w-md text-muted">{{ __('store.error_500_text') }}</p>
            </x-electric.scene>

            <div class="mt-8 text-center">
                <x-btn :href="url('/')">{{ __('store.back_to_home') }}</x-btn>
            </div>
        </div>
    </section>
@endsection
