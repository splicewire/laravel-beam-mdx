<?php

namespace Splicewire\Beam\Mdx\Frontmatter\Contracts;

use Splicewire\Beam\Mdx\Frontmatter\ParsedFrontmatter;

/**
 * Capability — "I know how to build myself from a parsed block."
 *
 * Optional, because hydration is not always the shape's own job: a `spatie/laravel-data` shape is built
 * by the data package's own pipeline, and a shape may legitimately be constructed by a consumer that
 * already holds the values. {@see \Splicewire\Beam\Mdx\Frontmatter\FrontmatterResolver::hydrate()} uses
 * this when present and throws a named, actionable error when it is not — rather than guessing at a
 * constructor signature, which would turn a declaration mistake into a confusing runtime one.
 */
interface HydratesFromFrontmatter
{
    public static function fromFrontmatter(ParsedFrontmatter $parsed): static;
}
