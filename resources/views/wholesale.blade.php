@extends('layouts.app')

@section('content')
    <section class="mx-auto max-w-3xl px-4 py-10">
        <h1 class="text-3xl font-extrabold text-ink">{{ __('store.wholesale_title') }}</h1>
        <p class="mt-2 text-muted">{{ __('store.wholesale_hint') }}</p>
        <div class="mt-6 rounded-xl bg-surface p-5 shadow-sm">
            <livewire:wholesale-form />
        </div>
    </section>
@endsection
