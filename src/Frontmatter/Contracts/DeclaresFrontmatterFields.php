<?php

namespace Splicewire\Beam\Mdx\Frontmatter\Contracts;

/**
 * Capability — "these are the canonical keys I claim."
 *
 * Optional. Its only consumer is the advisory audit that reports an authored key no shape claims; a
 * shape declining this is simply **not audited**, and the audit says so in its own output rather than
 * reporting a zero that cannot be told apart from "nothing to report".
 *
 * Keys are the CANONICAL (snake_case) form — the same form
 * {@see \Splicewire\Beam\Mdx\Frontmatter\ParsedFrontmatter::$fields} carries.
 */
interface DeclaresFrontmatterFields
{
    /** @return list<string> canonical keys this shape claims */
    public static function fieldNames(): array;
}
