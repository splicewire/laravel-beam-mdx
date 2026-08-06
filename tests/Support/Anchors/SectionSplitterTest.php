<?php

namespace Splicewire\Beam\Mdx\Tests\Support\Anchors;

use PHPUnit\Framework\TestCase;
use Splicewire\Beam\Mdx\Support\Anchors\SectionSplitter;

class SectionSplitterTest extends TestCase
{
    public function test_splits_linear_text_into_one_slice_per_header(): void
    {
        $s1 = "3-301.11  Preventing Contamination from Hands.\nFood employees shall wash.\n";
        $s2 = "4-101.11  Multiuse Materials.\nMaterials shall be safe.\n";
        $text = $s1.$s2;

        $sections = SectionSplitter::split($text, [
            ['offset' => 0, 'sectno' => '3-301.11'],
            ['offset' => strlen($s1), 'sectno' => '4-101.11'],
        ], strlen($text));

        $this->assertCount(2, $sections);
        $this->assertSame('3-301.11', $sections[0]['sectno']);
        $this->assertSame('Preventing Contamination from Hands.', $sections[0]['title']);
        $this->assertStringContainsString('Food employees shall wash.', $sections[0]['text']);
        $this->assertStringNotContainsString('Multiuse Materials', $sections[0]['text']);
        $this->assertSame('4-101.11', $sections[1]['sectno']);
        $this->assertSame('Multiuse Materials.', $sections[1]['title']);
    }

    public function test_excludes_the_demoted_tail(): void
    {
        $s1 = "3-301.11  Preventing Contamination.\nBody one.\n";
        $s2 = "4-101.11  Multiuse Materials.\nBody two.\n";
        $annex = "1-201.10  Demoted back-matter that re-cites.\nAnnex body.\n";
        $text = $s1.$s2.$annex;
        $demotionOffset = strlen($s1.$s2);

        $sections = SectionSplitter::split($text, [
            ['offset' => 0, 'sectno' => '3-301.11'],
            ['offset' => strlen($s1), 'sectno' => '4-101.11'],
        ], $demotionOffset);

        $this->assertCount(2, $sections);
        $names = array_column($sections, 'sectno');
        $this->assertSame(['3-301.11', '4-101.11'], $names);
        $this->assertStringNotContainsString('Annex body', $sections[1]['text']);
    }

    public function test_last_section_is_capped_at_the_demotion_offset(): void
    {
        $s1 = "2-102.12  Certified Food Protection Manager.\nThe person in charge shall be certified.\n";
        $tail = 'GARBAGE TRAILING CONTENT THAT SHOULD NOT BE INCLUDED';
        $text = $s1.$tail;

        $sections = SectionSplitter::split($text, [
            ['offset' => 0, 'sectno' => '2-102.12'],
        ], strlen($s1));

        $this->assertCount(1, $sections);
        $this->assertStringNotContainsString('GARBAGE', $sections[0]['text']);
    }

    public function test_empty_headers_yields_no_sections(): void
    {
        $this->assertSame([], SectionSplitter::split('anything', [], 8));
    }
}
