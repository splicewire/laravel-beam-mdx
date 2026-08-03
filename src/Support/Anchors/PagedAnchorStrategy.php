<?php

namespace Splicewire\Beam\Mdx\Support\Anchors;

/**
 * The universal-fallback Anchor Strategy: every PDF affords a page number via the
 * `pdftotext` form-feeds, so a chunk is anchored to the page its start falls on.
 */
class PagedAnchorStrategy implements AnchorStrategy
{
    public function __construct(protected PageMap $pageMap) {}

    public function metaFor(int $startOffset, int $endOffset): array
    {
        return ['page' => $this->pageMap->pageAt($startOffset)];
    }

    public function annotate(string $chunkText, int $startOffset): string
    {
        return $chunkText;
    }
}
