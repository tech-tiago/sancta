<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\BibleBook;
use App\Models\Church;
use App\Models\Faq;
use App\Models\Page;
use App\Models\Plan;
use App\Models\Testimonial;
use App\Services\PremiumContent;
use App\Services\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SiteController extends Controller
{
    public function home(Settings $settings): View
    {
        return view('site.home', [
            'plans' => Plan::withPricing(Plan::query()->active()->where('available_on_web', true)->get()),
            'faqs' => Faq::query()->for('site')->limit(8)->get(),
            'testimonials' => Testimonial::query()->where('is_active', true)->orderBy('sort_order')->limit(6)->get(),
            'stats' => cache()->remember('site.stats', 3600, fn () => [
                'churches' => Church::query()->published()->count(),
                'books' => BibleBook::query()->count(),
            ]),
            'settings' => $settings,
        ]);
    }

    public function plans(Settings $settings, PremiumContent $premium): View
    {
        return view('site.plans', [
            // Economia calculada contra o plano mensal ativo do MESMO canal (site).
            'plans' => Plan::withPricing(Plan::query()->active()->where('available_on_web', true)->get()),
            'faqs' => Faq::query()->for('site')->get(),
            'premium' => $premium->toArray(),
            'settings' => $settings,
        ]);
    }

    public function page(string $slug): View
    {
        return view('site.page', [
            'page' => Page::query()->where('slug', $slug)->where('is_published', true)->firstOrFail(),
        ]);
    }

    public function theme(Request $request): RedirectResponse
    {
        $data = $request->validate(['theme' => ['required', Rule::in(['light', 'dark', 'system'])]]);

        if ($user = $request->user()) {
            $user->forceFill(['theme' => $data['theme']])->save();
        }

        return back()->withCookie(Cookie::forever('sancta_theme', $data['theme'], null, null, null, true, false, false, 'lax'));
    }

    public function sitemap(): Response
    {
        $xml = cache()->remember('site.sitemap', 3600, function () {
            $urls = collect([route('home'), route('plans'), route('gift.index'), route('live'), route('bible.index'), route('churches.index')]);
            BibleBook::query()->orderBy('position')->get(['slug'])->each(fn ($b) => $urls->push(route('bible.chapter', [$b->slug, 1])));
            Church::query()->published()->get(['slug'])->each(fn ($c) => $urls->push(route('churches.show', $c->slug)));
            Page::query()->where('is_published', true)->get(['slug'])->each(fn ($p) => $urls->push(route('pages.show', $p->slug)));

            return '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
                .$urls->map(fn ($u) => '<url><loc>'.e($u).'</loc></url>')->implode('')
                .'</urlset>';
        });

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }
}
