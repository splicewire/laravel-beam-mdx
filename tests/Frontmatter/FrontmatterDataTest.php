<?php

namespace Splicewire\Beam\Mdx\Tests\Frontmatter;

use PHPUnit\Framework\Attributes\Test;
use Schemastud\DataSchemas\Contracts\SchemaIdentity;
use Spatie\LaravelData\Mappers\CamelCaseMapper;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Splicewire\Beam\Mdx\Frontmatter\Contracts\Frontmatter;
use Splicewire\Beam\Mdx\Frontmatter\FrontmatterData;
use Splicewire\Beam\Mdx\Frontmatter\FrontmatterParser;
use Splicewire\Beam\Mdx\Tests\TestCase;

/**
 * The shipped default shape (frontmatter-declaration-seam ticket 03).
 *
 * The load-bearing tests are the two that run **under a host global that disagrees with the pin**. A
 * class-level `#[MapInputName]` is only proven by a run where the ambient
 * `data.name_mapping_strategy.input` says something else — and the estate's hosts genuinely disagree
 * (`CamelCaseMapper` at 3 hosts, `SnakeCaseMapper` at 2, absent at 16 of 21). Without those two, the
 * pin looks redundant at any single host and a later reader deletes it.
 */
class FrontmatterDataTest extends TestCase
{
    #[Test]
    public function the_data_config_is_loaded(): void
    {
        // Guards the testbench-provider trap directly: without it, every assertion below fatals with a
        // message that names neither the provider nor the list it is missing from.
        $this->assertDataConfigIsLoaded();
    }

    #[Test]
    public function it_is_a_declared_frontmatter_shape_with_a_versioned_identity(): void
    {
        $this->assertInstanceOf(Frontmatter::class, new FrontmatterData);
        $this->assertInstanceOf(SchemaIdentity::class, new FrontmatterData);
        $this->assertSame('mdx/frontmatter', FrontmatterData::schemaName());
        $this->assertSame(1, FrontmatterData::schemaVersion());
    }

    #[Test]
    public function it_reads_snake_keys_under_a_camel_case_host_global(): void
    {
        config()->set('data.name_mapping_strategy.input', CamelCaseMapper::class);

        $data = FrontmatterData::from(['title' => 'T', 'layout' => 'site']);

        $this->assertSame('T', $data->title);
        $this->assertSame('site', $data->layout);
    }

    #[Test]
    public function it_reads_snake_keys_under_a_snake_case_host_global(): void
    {
        config()->set('data.name_mapping_strategy.input', SnakeCaseMapper::class);

        $data = FrontmatterData::from(['title' => 'T', 'layout' => 'site']);

        $this->assertSame('T', $data->title);
        $this->assertSame('site', $data->layout);
    }

    #[Test]
    public function hydrating_from_a_parsed_block_folds_the_authored_spelling(): void
    {
        $parsed = (new FrontmatterParser)->parse("---\ntitle: T\nlayout: site\n---\nbody\n");

        $data = FrontmatterData::fromFrontmatter($parsed);

        $this->assertSame('T', $data->title);
        $this->assertSame('site', $data->layout);
    }

    #[Test]
    public function undeclared_keys_are_retained_not_dropped(): void
    {
        $parsed = (new FrontmatterParser)->parse("---\ntitle: T\nnavOrder: 3\nschemaType: Article\n---\nbody\n");

        $data = FrontmatterData::fromFrontmatter($parsed);

        // Canonical keys, and the format-owned fields excluded — these belong to somebody else
        // (beam-ux reads nav_order; the JS plane reads schemaType at build time).
        $this->assertSame(['nav_order' => '3', 'schema_type' => 'Article'], $data->unknown());
    }

    #[Test]
    public function it_declares_the_fields_it_claims(): void
    {
        $this->assertSame(['title', 'layout', 'template'], FrontmatterData::fieldNames());
    }

    #[Test]
    public function it_resolves_and_hydrates_through_the_resolver_as_the_configured_default(): void
    {
        config()->set('beam.mdx.frontmatter.shape', FrontmatterData::class);

        $parsed = (new FrontmatterParser)->parse("---\ntitle: T\n---\nbody\n");
        $shape = app(\Splicewire\Beam\Mdx\Frontmatter\FrontmatterResolver::class)->hydrate($parsed);

        $this->assertInstanceOf(FrontmatterData::class, $shape);
        $this->assertSame('T', $shape->title);
    }
}
