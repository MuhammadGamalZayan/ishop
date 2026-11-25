<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\NewsletterSubscriber;
use App\Models\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Content pages from the admin panel, the Contact form and newsletter sign-up.
 * Page bodies are written by staff in the panel's rich editor and shown as
 * HTML; Filament's editor only produces a safe subset of tags.
 */
class PageController extends Controller
{
    public function show(string $slug): Response
    {
        $page = Page::query()->published()->where('slug', $slug)->firstOrFail();

        return Inertia::render('shop/page', [
            'page' => [
                'title' => $page->title,
                'body' => $page->body,
                'template' => $page->template,
                'meta_description' => $page->meta_description,
            ],
        ]);
    }

    public function contact(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'message' => ['required', 'string', 'min:10', 'max:3000'],
        ]);

        ContactMessage::create($data + ['locale' => app()->getLocale()]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('shop.contact.sent')]);

        return back();
    }

    public function subscribe(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:255']]);

        // Signing up twice is fine: same thank-you, no duplicate, no hint either way.
        NewsletterSubscriber::query()->firstOrCreate(
            ['email' => mb_strtolower(trim($data['email']))],
            ['locale' => app()->getLocale()],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('shop.newsletter.thanks')]);

        return back();
    }
}
