<?php

namespace Splicewire\Beam\Mdx\Frontmatter\Contracts;

/**
 * Capability — "hand me the keys I did not declare, rather than dropping them."
 *
 * Optional, and declining is the ordinary case. Frontmatter is open-ended: authors add keys, and some
 * are read by an entirely different consumer (the JS plane reads several of these files itself, at
 * build time). A shape that wants to round-trip or forward those keys implements this.
 */
interface RetainsUnknownFields
{
    /** @return array<string, string> canonical key => value, for keys this shape did not declare */
    public function unknown(): array;
}
