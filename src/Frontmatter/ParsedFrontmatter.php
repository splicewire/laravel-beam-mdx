<?php

namespace Splicewire\Beam\Mdx\Frontmatter;

/**
 * The result of reading one `---` block: the CANONICAL fields, the fields exactly as the author
 * wrote them, and the body with the block stripped.
 *
 * Both key sets are carried on purpose and neither is redundant.
 *
 *  - {@see $fields} is what a consumer reads. Keys are canonicalized (see
 *    {@see FrontmatterParser::canonicalize()}), so `navOrder:` and `nav_order:` arrive as the same
 *    field and a consumer never has to know which spelling an author preferred.
 *  - {@see $raw} is what the author typed. It is the only evidence of the authored spelling once
 *    canonicalization has run, and it is what an audit needs in order to report a key that no
 *    declared shape claims. Discarding it would make that audit impossible to write.
 */
class ParsedFrontmatter
{
    /**
     * @param  array<string, string>  $fields  canonical keys → values
     * @param  array<string, string>  $raw  keys exactly as authored → values
     * @param  string  $content  the source with the leading `---` block removed
     */
    public function __construct(
        public array $fields = [],
        public array $raw = [],
        public string $content = '',
    ) {}

    /** Whether the source carried a `---` block at all (an empty block is still a block). */
    public function hasBlock(): bool
    {
        return $this->raw !== [];
    }
}
