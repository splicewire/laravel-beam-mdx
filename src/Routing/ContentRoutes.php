<?php

namespace Splicewire\Beam\Mdx\Routing;

use Illuminate\Routing\Route as RouteInstance;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Splicewire\Beam\Mdx\Mdx;

/**
 * The catch-all content/essay route macros. Each satellite used to hand-write the same
 * ~5-line gated slug route; these collapse it to one call that carries the draft gate,
 * so the "drafts 404 outside the preview allowlist" convention is enforced identically
 * everywhere and can't drift per satellite.
 */
class ContentRoutes
{
    /** Register the `Route::beamMdxShow()` and `Route::beamMdxPage()` macros. */
    public static function registerMacros(): void
    {
        if (! Route::hasMacro('beamMdxShow')) {
            Route::macro('beamMdxShow', function (
                string $prefix,
                string $page,
                string $slugPattern = '[a-z0-9-]+',
            ): RouteInstance {
                // GET /{prefix}/{slug}: 404 a draft slug outside the preview allowlist
                // (belt to the build-time bundle exclusion), else render the Inertia page
                // with the fully-qualified content name.
                return Route::get($prefix.'/{slug}', function (string $slug) use ($prefix, $page) {
                    $name = $prefix.'/'.$slug;

                    abort_unless(Mdx::isVisible($name), 404);

                    return Inertia::render($page, ['slug' => $name]);
                })->where('slug', $slugPattern);
            });
        }

        if (! Route::hasMacro('beamMdxPage')) {
            Route::macro('beamMdxPage', function (
                string $uri,
                string $page,
                string $name,
            ): RouteInstance {
                // A single named content page (about, resume): gated the same way, so even a
                // root page marked `draft: true` 404s outside preview.
                return Route::get($uri, function () use ($page, $name) {
                    abort_unless(Mdx::isVisible($name), 404);

                    return Inertia::render($page, ['slug' => $name]);
                });
            });
        }
    }
}
