<?php

namespace Splicewire\Beam\Mdx\Tests\Support\Anchors;

use PHPUnit\Framework\TestCase;
use Splicewire\Beam\Mdx\Support\Anchors\SectionRangeFilter;

class SectionRangeFilterTest extends TestCase
{
    /** @return array<int, array<string, mixed>> */
    protected function sections(): array
    {
        return [
            ['sectno' => '1-201.10', 'name' => 'ch1'],
            ['sectno' => '2-102.12', 'name' => 'ch2'],
            ['sectno' => '3-501.16', 'name' => 'ch3'],
            ['sectno' => '4-301.11', 'name' => 'ch4'],
            ['sectno' => '5-203.11', 'name' => 'ch5'],
            ['sectno' => '8-103.10', 'name' => 'ch8'],
            ['sectno' => null, 'name' => 'back-matter'],
            ['name' => 'no-sectno-key'],
            ['sectno' => '', 'name' => 'blank'],
        ];
    }

    public function test_minimal_keeps_only_chapters_two_three_four(): void
    {
        $filter = new SectionRangeFilter([2, 3, 4]);

        $kept = array_column($filter->filter($this->sections()), 'name');

        $this->assertSame(['ch2', 'ch3', 'ch4'], $kept);
    }

    public function test_sections_without_a_sectno_are_dropped(): void
    {
        $filter = new SectionRangeFilter([2, 3, 4]);

        $kept = array_column($filter->filter($this->sections()), 'name');

        $this->assertNotContains('back-matter', $kept);
        $this->assertNotContains('no-sectno-key', $kept);
        $this->assertNotContains('blank', $kept);
    }

    public function test_empty_chapter_set_keeps_all_sectioned_sections(): void
    {
        $filter = new SectionRangeFilter([]);

        $kept = array_column($filter->filter($this->sections()), 'name');

        $this->assertSame(['ch1', 'ch2', 'ch3', 'ch4', 'ch5', 'ch8'], $kept);
        $this->assertNotContains('back-matter', $kept);
    }

    public function test_malformed_sectno_does_not_throw_and_is_excluded(): void
    {
        $filter = new SectionRangeFilter([2, 3, 4]);

        $kept = $filter->filter([
            ['sectno' => 'not-a-section', 'name' => 'garbage'],
            ['sectno' => '3-501.16', 'name' => 'ch3'],
        ]);

        $this->assertSame(['ch3'], array_column($kept, 'name'));
    }

    public function test_chapter_of_parses_leading_integer(): void
    {
        $this->assertSame(3, SectionRangeFilter::chapterOf('3-501.16'));
        $this->assertSame(12, SectionRangeFilter::chapterOf('12-101.01'));
        $this->assertSame(8, SectionRangeFilter::chapterOf('8-103.10'));
        $this->assertNull(SectionRangeFilter::chapterOf(null));
        $this->assertNull(SectionRangeFilter::chapterOf(''));
        $this->assertNull(SectionRangeFilter::chapterOf('annex'));
    }
}
