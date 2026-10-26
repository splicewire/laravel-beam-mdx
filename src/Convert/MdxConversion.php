<?php

namespace Splicewire\Beam\Mdx\Convert;

/** What converting a host's MDX files did, or would do on a dry run (ADR-0233 d8, d9). Paths are relative to the root. */
final class MdxConversion
{
    /**
     * @param  list<string>  $converted  pages rewritten (or that a dry run would rewrite)
     * @param  list<string>  $unchanged  pages already canonical
     * @param  array<string, string>  $leftOut  pages left as they are, each with why
     * @param  array<string, list<string>>  $legal  pages that now place a shipped legal component, by its name
     * @param  array<string, string>  $hostComponents  each host fragment a page includes, with the component it becomes
     * @param  list<string>  $unusedFragments  fragments no page includes: reported, not converted
     */
    public function __construct(
        public array $converted = [],
        public array $unchanged = [],
        public array $leftOut = [],
        public array $legal = [],
        public array $hostComponents = [],
        public array $unusedFragments = [],
    ) {}
}
