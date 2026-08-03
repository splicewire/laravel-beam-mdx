<?php

namespace Splicewire\Beam\Mdx\Support\Anchors;

/**
 * Slices the linear PDF text into one span per validated `sectioned` header
 * (app ADR-0014). Each span runs from its header offset to the next header (or
 * the demotion offset, whichever is first), so the demoted annex/back-matter
 * tail is never emitted as a section. The title is the header line's remainder
 * after the section number; the text is the whole span, heading included.
 *
 * This is a pure transform — it derives no anchors and reads no document state
 * beyond the headers SectionedAnchorStrategy already validated.
 */
class SectionSplitter
{
    /**
     * @param  array<int, array{offset: int, sectno: string}>  $headers  document-order validated headers
     * @return array<int, array{sectno: string, title: string, text: string}>
     */
    public static function split(string $linearText, array $headers, int $demotionOffset): array
    {
        $headers = array_values($headers);
        $count = count($headers);
        $sections = [];

        foreach ($headers as $i => $header) {
            $start = $header['offset'];
            if ($start >= $demotionOffset) {
                continue;
            }

            $next = $i + 1 < $count ? $headers[$i + 1]['offset'] : $demotionOffset;
            $end = min($next, $demotionOffset);
            $slice = substr($linearText, $start, $end - $start);

            $newline = strpos($slice, "\n");
            $firstLine = $newline === false ? $slice : substr($slice, 0, $newline);
            $title = trim(substr($firstLine, strlen($header['sectno'])));

            $sections[] = [
                'sectno' => $header['sectno'],
                'title' => $title,
                'text' => trim($slice),
            ];
        }

        return $sections;
    }
}
