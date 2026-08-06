<?php

namespace Splicewire\Beam\Mdx\Tests\Support\Anchors;

use PHPUnit\Framework\TestCase;
use Splicewire\Beam\Mdx\Support\Anchors\PageMap;
use Splicewire\Beam\Mdx\Support\Anchors\SectionedAnchorStrategy;

class SectionedAnchorStrategyTest extends TestCase
{
    protected function strategy(string $text): SectionedAnchorStrategy
    {
        $pageMap = new PageMap([['offset' => 0, 'page' => 1]]);

        return new SectionedAnchorStrategy($pageMap, $text, '\d-\d{3}\.\d{2}');
    }

    public function test_carry_forward_labels_offsets_including_leading_text(): void
    {
        $text = "1-201.10   Statement of Application.\nLeading body with no header of its own.\n3-301.11   Preventing Contamination from Hands.\nWash hands.";
        $strategy = $this->strategy($text);

        $this->assertTrue($strategy->isViable());
        $this->assertSame('1-201.10', $strategy->metaFor(0, 5)['sectno']);

        // Body text after the first header but before the second still inherits it.
        $bodyOffset = strpos($text, 'Leading body');
        $this->assertSame('1-201.10', $strategy->metaFor($bodyOffset, $bodyOffset + 5)['sectno']);

        $secondOffset = strpos($text, '3-301.11');
        $this->assertSame('3-301.11', $strategy->metaFor($secondOffset, $secondOffset + 5)['sectno']);
    }

    public function test_inline_cross_reference_is_not_a_header(): void
    {
        $text = "1-201.10   Statement of Application.\nSee 3-301.11 and 4-101.11 for related duties.";
        $strategy = $this->strategy($text);

        $crossRefOffset = strpos($text, 'See');
        // Still governed by the only real header, not the inline citation.
        $this->assertSame('1-201.10', $strategy->metaFor($crossRefOffset, $crossRefOffset + 5)['sectno']);
    }

    public function test_out_of_order_region_demotes_to_paged(): void
    {
        $text = "1-201.10   Statement of Application.\n2-101.11   Assignment.\n3-301.11   Preventing Contamination.\n1-201.10   Annex Restatement.\nMore annex text.";
        $strategy = $this->strategy($text);

        // The monotonic front stays sectioned.
        $this->assertSame('3-301.11', $strategy->metaFor(strpos($text, '3-301.11'), strpos($text, '3-301.11') + 5)['sectno']);

        // The out-of-order restatement (and everything after) demotes: no sectno.
        $annexOffset = strrpos($text, '1-201.10');
        $this->assertArrayNotHasKey('sectno', $strategy->metaFor($annexOffset, $annexOffset + 5));

        $tailOffset = strpos($text, 'More annex text');
        $this->assertArrayNotHasKey('sectno', $strategy->metaFor($tailOffset, $tailOffset + 5));
    }

    public function test_annotate_inserts_leading_and_inline_breadcrumbs(): void
    {
        $text = "1-201.10   Statement.\n3-301.11   Preventing Contamination.\nWash hands.";
        $strategy = $this->strategy($text);

        // A chunk starting inside the first section, spanning into the second.
        $start = strpos($text, 'Statement');
        $chunk = substr($text, $start);
        $annotated = $strategy->annotate($chunk, $start);

        $this->assertStringStartsWith('[§ 1-201.10]', $annotated);
        $this->assertStringContainsString('[§ 3-301.11]', $annotated);
    }
}
