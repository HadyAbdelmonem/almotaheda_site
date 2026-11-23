<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\Contracts\View\View;

class PageController extends Controller
{
    /** Static page from the `pages` table (spec §6.9). */
    public function show(string $slug): View
    {
        $page = Page::query()->where('slug', $slug)->where('is_active', true)->firstOrFail();

        return view('pages.show', [
            'page' => $page,
            'title' => $page->meta_title ?: $page->title,
            'metaDescription' => $page->meta_description ?: $page->title,
            'canonical' => route('pages.show', $page->slug),
        ]);
    }
}
