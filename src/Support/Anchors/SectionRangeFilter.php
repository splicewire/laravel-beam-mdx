<?php

namespace Splicewire\Beam\Mdx\Support\Anchors;

/**
 * Selects sections by chapter for a corpus seeding profile (e.g. the Food Code
 * `minimal` profile = Chapters 2–4). A section's chapter is the leading integer of
 * its `sectno` tuple (`3-501.16` → chapter 3).
 *
 * This is a generic volume-control filter over already-anchored sections: it derives
 * no Citation Anchors and applies no corpus-specific boundary rule (app ADR-0014).
 */
class SectionRangeFilter
{
    /** @param  array<int, int>  $chapters  Empty means "all chapters". */
    public function __construct(protected array $chapters = []) {}

    /**
     * Keep sections whose `sectno`'s leading chapter is in the configured set. Sections
     * with a missing or unparseable `sectno` are dropped. An empty chapter set keeps
     * every section that has a parseable `sectno`.
     *
     * @param  array<int, array<string, mixed>>  $sections  each item should carry a 'sectno'
     * @return array<int, array<string, mixed>>
     */
    public function filter(array $sections): array
    {
        return array_values(array_filter($sections, function (array $section): bool {
            $chapter = self::chapterOf($section['sectno'] ?? null);

            if ($chapter === null) {
                return false;
            }

            return $this->chapters === [] || in_array($chapter, $this->chapters, true);
        }));
    }

    /**
     * The chapter is the leading integer of the section number tuple, or null when the
     * value has no leading digits (e.g. blank, or demoted back-matter with no section).
     */
    public static function chapterOf(?string $sectno): ?int
    {
        if ($sectno === null) {
            return null;
        }

        $parts = preg_split('/\D+/', $sectno, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return isset($parts[0]) ? (int) $parts[0] : null;
    }
}
