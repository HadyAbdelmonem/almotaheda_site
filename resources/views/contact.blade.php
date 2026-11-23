@extends('layouts.app')

@section('content')
    <section class="mx-auto max-w-3xl px-4 py-10">
        <h1 class="text-3xl font-extrabold text-ink">{{ __('store.contact_us') }}</h1>
        <p class="mt-2 text-muted">{{ __('store.contact_hint') }}</p>
        <div class="mt-6 rounded-xl bg-surface p-5 shadow-sm">
            <livewire:contact-form />
        </div>
    </section>
@endsection
