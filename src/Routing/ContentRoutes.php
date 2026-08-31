<?php

namespace Splicewire\Beam\Mdx\Routing;

use Illuminate\Routing\Route as RouteInstance;
use Illuminate\Support\Facades\Route;
use Splicewire\Beam\Mdx\Mdx;

/**
 * The catch-all content/essay route macros. Each satellite used to hand-write the same
 * ~5-line gated slug route; these collapse it to one call that carries the draft gate,
 * so the "drafts 404 outside the preview allowlist" convention is enforced identically
 * everywhere and can't drift per satellite.
 */
class ContentRoutes
{
    /**
     * Register the `Route::beamMdxShow()` and `Route::beamMdxPage()` macros.
     *
     * ⚠️ Both macros render through Inertia, which is a **suggested** dependency rather than a required
     * one (see {@see MissingInertia}). Registering the macros is free; only *mounting* one needs the
     * package, and the guard fires at mount rather than at boot so a host that never calls them is
     * unaffected.
     */
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
                ContentRoutes::assertInertiaInstalled('beamMdxShow');

                return Route::get($prefix.'/{slug}', function (string $slug) use ($prefix, $page) {
                    $name = $prefix.'/'.$slug;

                    abort_unless(Mdx::isVisible($name), 404);

                    return \Inertia\Inertia::render($page, ['slug' => $name]);
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
                ContentRoutes::assertInertiaInstalled('beamMdxPage');

                return Route::get($uri, function () use ($page, $name) {
                    abort_unless(Mdx::isVisible($name), 404);

                    return \Inertia\Inertia::render($page, ['slug' => $name]);
                });
            });
        }
    }

    /**
     * Fail loudly, at the mount, when the suggested Inertia package is absent.
     *
     * Checked here rather than at boot because mounting a macro is the host's own deliberate act — the
     * one moment the answer is both knowable and actionable. A host that registers this package and
     * never mounts a content route needs no frontend adapter at all.
     *
     * ⚠️ `public` and called by class name rather than `self::`, because Laravel binds a macro closure
     * to the **Router** instance — which rebinds `self` too, so `self::assertInertiaInstalled()` inside
     * the closure resolves against Router and dies as `Attribute [assertInertiaInstalled] does not
     * exist`. Not a style choice.
     */
    public static function assertInertiaInstalled(string $macro): void
    {
        if (! class_exists(\Inertia\Inertia::class)) {
            throw MissingInertia::forMacro($macro);
        }
    }
}
