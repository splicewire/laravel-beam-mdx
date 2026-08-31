<?php

namespace Splicewire\Beam\Mdx\Routing;

use RuntimeException;

/**
 * Raised when a host mounts an MDX content route macro without `inertiajs/inertia-laravel` installed.
 *
 * Inertia is a **suggested**, not required, dependency of this package: it is reached from exactly one
 * file, for the two `Inertia::render()` calls inside these opt-in macros, and a host that never calls
 * them never needs it. Requiring it forced a frontend adapter onto every consumer — including packages
 * with no frontend at all, which is what kept `splicewire/laravel-beam-mcp` from sharing this package's
 * frontmatter grammar.
 *
 * The failure is loud and names the fix, because "did the host install an optional package" is
 * knowable only at the call, and the call is the host's own deliberate act.
 */
class MissingInertia extends RuntimeException
{
    public static function forMacro(string $macro): self
    {
        return new self(
            "Route::{$macro}() renders an Inertia page, but inertiajs/inertia-laravel is not installed. "
            .'It is a suggested dependency of splicewire/laravel-beam-mdx — install it, or do not mount '
            .'this macro.'
        );
    }
}
