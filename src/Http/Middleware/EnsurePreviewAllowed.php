<?php

namespace Splicewire\Beam\Mdx\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Splicewire\Beam\Mdx\Mdx;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate a whole route (or group) to preview-allowlisted environments only. A
 * structurally-always-draft surface — e.g. the broadcasts ledger, which carries no
 * published date — mounts behind this: production 404s the entire surface, a preview env
 * lets it through. Registered as the `beam-mdx.preview` middleware alias.
 */
class EnsurePreviewAllowed
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(Mdx::previewAllowed(), 404);

        return $next($request);
    }
}
