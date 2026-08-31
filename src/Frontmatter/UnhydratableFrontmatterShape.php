<?php

namespace Splicewire\Beam\Mdx\Frontmatter;

use RuntimeException;
use Splicewire\Beam\Mdx\Frontmatter\Contracts\HydratesFromFrontmatter;

/**
 * Raised when a caller asks {@see FrontmatterResolver::hydrate()} for a shape that declines
 * {@see HydratesFromFrontmatter} and for which no hydration strategy is registered.
 *
 * Distinct from the `InvalidArgumentException` the resolver raises for a bad declaration, because this
 * one is **not** necessarily an author error: resolving such a shape is legal (declining a capability
 * is an answer), and only *hydrating* it is unsupported. The message names the two ways out.
 */
class UnhydratableFrontmatterShape extends RuntimeException
{
    public static function for(string $shape): self
    {
        return new self(
            "Frontmatter shape [{$shape}] cannot be hydrated: it does not implement "
            .HydratesFromFrontmatter::class.' and no hydration strategy is registered for it. '
            .'Either implement that capability, or register a strategy with '
            .'FrontmatterResolver::hydrateUsing().'
        );
    }
}
