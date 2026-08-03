<?php

namespace Splicewire\Beam\Mdx\Support\Anchors;

/**
 * Resolves a numbered-section Citation Anchor (e.g. `§ 3-301.11`) by carry-forward
 * of the last-seen line-start section header over the whole linear text, then
 * stamps naive chunks by overlap (app ADR-0014).
 *
 * Self-validation is per region, not whole-document: the strategy keeps the maximal
 * monotonic span of headers as `sectioned` and demotes the out-of-order remainder
 * (annexes/back-matter that re-cite sections) to `paged` — no corpus-specific
 * boundary rule. Beyond the demotion offset it defers to the page anchor.
 */
class SectionedAnchorStrategy implements AnchorStrategy
{
    /** @var array<int, array{offset: int, sectno: string, tuple: array<int, int>}> */
    protected array $headers = [];

    protected int $demotionOffset;

    public function __construct(
        protected PageMap $pageMap,
        string $linearText,
        string $marker,
    ) {
        $this->demotionOffset = strlen($linearText);
        $this->scan($linearText, $marker);
    }

    public function metaFor(int $startOffset, int $endOffset): array
    {
        $meta = ['page' => $this->pageMap->pageAt($startOffset)];

        $sectno = $this->sectionAt($startOffset);
        if ($sectno !== null) {
            $meta['sectno'] = $sectno;
            $meta['sectnos'] = $this->sectionsInRange($startOffset, $endOffset);
        }

        return $meta;
    }

    public function annotate(string $chunkText, int $startOffset): string
    {
        if ($startOffset >= $this->demotionOffset) {
            return $chunkText;
        }

        $carried = $this->sectionAt($startOffset);
        $inside = array_filter(
            $this->headers,
            fn ($h) => $h['offset'] > $startOffset && $h['offset'] < $this->demotionOffset
                && $h['offset'] < $startOffset + strlen($chunkText),
        );

        if ($carried === null && empty($inside)) {
            return $chunkText;
        }

        $result = $carried !== null ? "[§ {$carried}] " : '';
        $cursor = 0;
        foreach ($inside as $h) {
            $rel = $h['offset'] - $startOffset;
            $result .= substr($chunkText, $cursor, $rel - $cursor);
            $result .= "[§ {$h['sectno']}] ";
            $cursor = $rel;
        }
        $result .= substr($chunkText, $cursor);

        return $result;
    }

    /**
     * Whether any header validated as `sectioned`. When false the strategy behaves
     * exactly like `paged` (every offset resolves to null).
     */
    public function isViable(): bool
    {
        return ! empty($this->headers);
    }

    /**
     * Section numbers that validated as `sectioned`, in document order. The
     * expected count in monotonic order is the operational tell that the strategy
     * vouched for the corpus (app ADR-0014).
     *
     * @return array<int, string>
     */
    public function sectionedSectionNumbers(): array
    {
        return array_map(fn ($h) => $h['sectno'], $this->headers);
    }

    /**
     * Char offset beyond which the strategy demoted to `paged`. Less than the text
     * length means a tail (annexes/back-matter) was demoted.
     */
    public function demotionOffset(): int
    {
        return $this->demotionOffset;
    }

    /**
     * The validated `sectioned` headers in document order, each as its char offset
     * and section number. Used to slice the linear text into per-section spans
     * (see SectionSplitter); excludes any header in the demoted tail.
     *
     * @return array<int, array{offset: int, sectno: string}>
     */
    public function validatedHeaders(): array
    {
        return array_map(
            fn ($h) => ['offset' => $h['offset'], 'sectno' => $h['sectno']],
            $this->headers,
        );
    }

    protected function sectionAt(int $offset): ?string
    {
        if ($offset >= $this->demotionOffset) {
            return null;
        }

        $sectno = null;
        foreach ($this->headers as $h) {
            if ($h['offset'] <= $offset) {
                $sectno = $h['sectno'];
            } else {
                break;
            }
        }

        return $sectno;
    }

    /** @return array<int, string> */
    protected function sectionsInRange(int $start, int $end): array
    {
        $sections = [];
        $carried = $this->sectionAt($start);
        if ($carried !== null) {
            $sections[] = $carried;
        }
        foreach ($this->headers as $h) {
            if ($h['offset'] > $start && $h['offset'] < $end && $h['offset'] < $this->demotionOffset) {
                $sections[] = $h['sectno'];
            }
        }

        return array_values(array_unique($sections));
    }

    protected function scan(string $text, string $marker): void
    {
        // A header is a line-start section number, then -layout column spacing
        // (2+ spaces), then a Title-cased title. The column gap is load-bearing: it
        // separates real headers from cross-references that `-layout` wrapped to a
        // line start (e.g. "3-401.15 Pf, or"), which use single inter-word spacing.
        $pattern = '/^[ \t]*('.$marker.')[ \t]{2,}\p{Lu}/mu';

        if (! preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE)) {
            return;
        }

        $found = [];
        foreach ($matches[1] as $match) {
            $found[] = [
                'offset' => $match[1],
                'sectno' => $match[0],
                'tuple' => $this->tuple($match[0]),
            ];
        }

        // Keep the maximal monotonic (non-decreasing) prefix; the first out-of-order
        // header begins the demoted region.
        $last = null;
        foreach ($found as $h) {
            if ($last !== null && $this->compareTuple($h['tuple'], $last) < 0) {
                $this->demotionOffset = $h['offset'];
                break;
            }
            $this->headers[] = $h;
            $last = $h['tuple'];
        }
    }

    /** @return array<int, int> */
    protected function tuple(string $sectno): array
    {
        return array_map('intval', preg_split('/\D+/', $sectno, -1, PREG_SPLIT_NO_EMPTY) ?: []);
    }

    /**
     * @param  array<int, int>  $a
     * @param  array<int, int>  $b
     */
    protected function compareTuple(array $a, array $b): int
    {
        $len = max(count($a), count($b));
        for ($i = 0; $i < $len; $i++) {
            $av = $a[$i] ?? 0;
            $bv = $b[$i] ?? 0;
            if ($av !== $bv) {
                return $av <=> $bv;
            }
        }

        return 0;
    }
}
