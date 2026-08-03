<?php

namespace Splicewire\Beam\Mdx\Support\Anchors;

/**
 * Derives a Fragment's Citation Anchor from the linear extracted text (app
 * ADR-0014). A strategy runs label-then-chunk: it is built once over the whole
 * linear text, then each naive chunk is *stamped* by overlapping its offset range
 * against the strategy's map. A strategy must vouch for itself and demote down the
 * fallback ladder (`sectioned` -> `paged`) where it cannot.
 */
interface AnchorStrategy
{
    /**
     * Anchor meta for a chunk spanning [$startOffset, $endOffset) of the linear text.
     *
     * @return array<string, mixed>
     */
    public function metaFor(int $startOffset, int $endOffset): array;

    /**
     * Annotate a chunk's text with inline anchor breadcrumbs before it reaches
     * triple/grounded extraction. Strategies with no inline anchor return the text
     * unchanged.
     */
    public function annotate(string $chunkText, int $startOffset): string;
}
