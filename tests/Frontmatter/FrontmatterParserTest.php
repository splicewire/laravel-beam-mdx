<?php

namespace Splicewire\Beam\Mdx\Tests\Frontmatter;

use PHPUnit\Framework\Attributes\Test;
use Splicewire\Beam\Mdx\Frontmatter\FrontmatterParser;
use Splicewire\Beam\Mdx\Tests\TestCase;

/**
 * The one frontmatter grammar (frontmatter-declaration-seam ticket 01).
 *
 * The load-bearing assertion is {@see camel_and_snake_keys_canonicalize_to_the_same_field}: five
 * hand-rolled readers each matched `navOrder` with `[A-Za-z0-9_-]+` and then looked up `nav_order`,
 * so the key parsed cleanly and was silently dropped. Canonicalizing here is what makes both
 * spellings mean the same thing before any consumer has an opinion.
 */
class FrontmatterParserTest extends TestCase
{
    private function parser(): FrontmatterParser
    {
        return new FrontmatterParser;
    }

    #[Test]
    public function camel_and_snake_keys_canonicalize_to_the_same_field(): void
    {
        $camel = $this->parser()->parse("---\nnavOrder: 3\nnavGroup: Knowledge\n---\nbody\n");
        $snake = $this->parser()->parse("---\nnav_order: 3\nnav_group: Knowledge\n---\nbody\n");

        $this->assertSame(['nav_order' => '3', 'nav_group' => 'Knowledge'], $camel->fields);
        $this->assertSame($camel->fields, $snake->fields);
    }

    #[Test]
    public function the_authored_spelling_survives_in_raw(): void
    {
        $parsed = $this->parser()->parse("---\nnavOrder: 3\n---\nbody\n");

        $this->assertSame(['nav_order' => '3'], $parsed->fields);
        $this->assertSame(['navOrder' => '3'], $parsed->raw);
    }

    #[Test]
    public function a_hyphenated_key_canonicalizes_too(): void
    {
        $parsed = $this->parser()->parse("---\nnav-group: Build\n---\nbody\n");

        $this->assertSame(['nav_group' => 'Build'], $parsed->fields);
        $this->assertSame(['nav-group' => 'Build'], $parsed->raw);
    }

    #[Test]
    public function a_file_with_no_block_yields_no_fields_and_the_whole_source_as_content(): void
    {
        $parsed = $this->parser()->parse("# Just a heading\n");

        $this->assertSame([], $parsed->fields);
        $this->assertSame([], $parsed->raw);
        $this->assertSame("# Just a heading\n", $parsed->content);
    }

    #[Test]
    public function crlf_line_endings_parse(): void
    {
        $parsed = $this->parser()->parse("---\r\nnavOrder: 3\r\n---\r\nbody\r\n");

        $this->assertSame(['nav_order' => '3'], $parsed->fields);
        $this->assertSame("body\r\n", $parsed->content);
    }

    #[Test]
    public function quotes_are_trimmed_and_a_value_may_contain_a_colon(): void
    {
        $parsed = $this->parser()->parse("---\ntitle: \"Import: a guide\"\n---\nbody\n");

        $this->assertSame(['title' => 'Import: a guide'], $parsed->fields);
    }

    #[Test]
    public function an_empty_value_is_an_empty_string_not_a_missing_key(): void
    {
        $parsed = $this->parser()->parse("---\ntitle:\n---\nbody\n");

        $this->assertArrayHasKey('title', $parsed->fields);
        $this->assertSame('', $parsed->fields['title']);
    }

    #[Test]
    public function the_content_has_the_block_stripped(): void
    {
        $parsed = $this->parser()->parse("---\ntitle: T\n---\n# Heading\n\nBody.\n");

        $this->assertSame("# Heading\n\nBody.\n", $parsed->content);
    }

    #[Test]
    public function a_line_that_is_not_a_key_value_pair_is_skipped(): void
    {
        $parsed = $this->parser()->parse("---\ntitle: T\nnot a pair\n---\nbody\n");

        $this->assertSame(['title' => 'T'], $parsed->fields);
    }
}
