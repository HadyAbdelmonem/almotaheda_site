@extends('layouts.app')

@section('content')
    <section class="mx-auto max-w-4xl px-4 py-10">
        <x-breadcrumbs :items="[['label' => $page->title, 'url' => null]]" />
        <h1 class="mt-4 text-3xl font-extrabold text-ink">{{ $page->title }}</h1>
        {{-- Admin-authored rich text, sanitized through the allow-list (spec §9). --}}
        <div class="prose-store mt-6 space-y-4 leading-[1.9] text-body">
            {!! \App\Support\HtmlSanitizer::clean($page->content) !!}
        </div>
    </section>
@endsection
