<?php

use App\Models\Medicine;
use App\Models\Page;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/robots.txt', function () {
    return response("User-agent: *\nAllow: /\nDisallow: /admin\nDisallow: /api\nDisallow: /cart\nDisallow: /checkout\nDisallow: /order-created\nDisallow: /track-order\nDisallow: /favourites\nSitemap: ".url('/sitemap.xml')."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'public, max-age=3600']);
});

Route::get('/.well-known/security.txt', function () {
    abort_unless(filled(config('services.security.contact_email')), 404);

    return response('Contact: mailto:'.config('services.security.contact_email')."\nPreferred-Languages: en\nCanonical: ".url('/.well-known/security.txt')."\nPolicy: ".url('/content/privacy-policy')."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'public, max-age=86400']);
});

Route::get('/sitemap.xml', function () {
    $entries = collect([
        ['url' => url('/'), 'updated' => now()],
        ['url' => url('/medicines'), 'updated' => Medicine::where('is_active', true)->max('updated_at') ?? now()],
        ['url' => url('/upload-prescription'), 'updated' => now()],
        ['url' => url('/doctor-consultation'), 'updated' => now()],
        ['url' => url('/health-checkups'), 'updated' => now()],
        ['url' => url('/contact'), 'updated' => now()],
    ]);

    Medicine::query()->where('is_active', true)->whereHas('category', fn ($query) => $query->where('is_active', true))->select(['slug', 'updated_at'])->orderBy('id')->each(
        fn (Medicine $medicine) => $entries->push(['url' => url('/medicines/'.$medicine->slug), 'updated' => $medicine->updated_at]),
    );
    Page::query()->where('is_active', true)->whereNotNull('published_at')->where('published_at', '<=', now())->select(['slug', 'updated_at'])->orderBy('id')->each(
        fn (Page $page) => $entries->push(['url' => url('/content/'.$page->slug), 'updated' => $page->updated_at]),
    );

    $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
    foreach ($entries as $entry) {
        $location = htmlspecialchars($entry['url'], ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $modified = $entry['updated']->toAtomString();
        $xml .= "  <url><loc>{$location}</loc><lastmod>{$modified}</lastmod></url>\n";
    }

    return response($xml.'</urlset>', 200, ['Content-Type' => 'application/xml; charset=UTF-8', 'Cache-Control' => 'public, max-age=3600']);
});
