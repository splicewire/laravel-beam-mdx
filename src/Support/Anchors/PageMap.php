<?php

namespace Splicewire\Beam\Mdx\Support\Anchors;

/**
 * Maps character offsets in the linear extracted text to 1-based page numbers,
 * built from the `\f` form-feed boundaries `pdftotext -layout` emits.
 */
class PageMap
{
    /**
     * @param  array<int, array{offset: int, page: int}>  $entries  Ascending by offset.
     */
    public function __construct(protected array $entries) {}

    public function pageAt(int $offset): int
    {
        $page = 1;
        foreach ($this->entries as $entry) {
            if ($entry['offset'] <= $offset) {
                $page = $entry['page'];
            } else {
                break;
            }
        }

        return $page;
    }

    public function pageCount(): int
    {
        return count($this->entries);
    }
}
