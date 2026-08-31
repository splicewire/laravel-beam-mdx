<?php

namespace Splicewire\Beam\Mdx\Tests\Frontmatter\Fixtures;

use Splicewire\Beam\Mdx\Frontmatter\Contracts\DeclaresFrontmatterFields;
use Splicewire\Beam\Mdx\Frontmatter\Contracts\Frontmatter;
use Splicewire\Beam\Mdx\Frontmatter\Contracts\HydratesFromFrontmatter;
use Splicewire\Beam\Mdx\Frontmatter\Contracts\RetainsUnknownFields;
use Splicewire\Beam\Mdx\Frontmatter\ParsedFrontmatter;

/** A shape implementing every capability — the maximal case. */
class FullShape implements DeclaresFrontmatterFields, Frontmatter, HydratesFromFrontmatter, RetainsUnknownFields
{
    /** @param array<string, string> $leftovers */
    public function __construct(public ?string $title = null, public array $leftovers = []) {}

    public static function fromFrontmatter(ParsedFrontmatter $parsed): static
    {
        return new static(
            title: $parsed->fields['title'] ?? null,
            leftovers: array_diff_key($parsed->fields, array_flip(static::fieldNames())),
        );
    }

    /** @return list<string> */
    public static function fieldNames(): array
    {
        return ['title'];
    }

    /** @return array<string, string> */
    public function unknown(): array
    {
        return $this->leftovers;
    }
}
