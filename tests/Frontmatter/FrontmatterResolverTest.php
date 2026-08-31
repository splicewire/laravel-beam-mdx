<?php

namespace Splicewire\Beam\Mdx\Tests\Frontmatter;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Splicewire\Beam\Mdx\Frontmatter\Contracts\Frontmatter;
use Splicewire\Beam\Mdx\Frontmatter\FrontmatterParser;
use Splicewire\Beam\Mdx\Frontmatter\FrontmatterResolver;
use Splicewire\Beam\Mdx\Frontmatter\UnhydratableFrontmatterShape;
use Splicewire\Beam\Mdx\Tests\Frontmatter\Fixtures\BareShape;
use Splicewire\Beam\Mdx\Tests\Frontmatter\Fixtures\FullShape;
use Splicewire\Beam\Mdx\Tests\Frontmatter\Fixtures\NotAShape;
use Splicewire\Beam\Mdx\Tests\TestCase;

/**
 * The override seam (frontmatter-declaration-seam ticket 02).
 *
 * Two rules are asserted here rather than merely documented, because both are the kind that a later
 * "cleanup" silently reverses:
 *
 *  - **Declining a capability is an ANSWER.** {@see BareShape} implements the marker and nothing else
 *    and must resolve without complaint.
 *  - **Resolution is per-call, never a singleton.** An unbound auto-resolvable singleton is this
 *    estate's recorded wrong-answer-not-error defect, so the identity assertion is the guard.
 */
class FrontmatterResolverTest extends TestCase
{
    private function resolver(): FrontmatterResolver
    {
        return $this->app->make(FrontmatterResolver::class);
    }

    #[Test]
    public function the_default_shape_comes_from_config(): void
    {
        config()->set('beam.mdx.frontmatter.shape', FullShape::class);

        $this->assertInstanceOf(FullShape::class, $this->resolver()->resolve());
    }

    #[Test]
    public function an_explicit_class_string_overrides_the_configured_default(): void
    {
        config()->set('beam.mdx.frontmatter.shape', FullShape::class);

        $this->assertInstanceOf(BareShape::class, $this->resolver()->resolve(BareShape::class));
    }

    #[Test]
    public function a_prebuilt_instance_is_used_as_is(): void
    {
        $shape = new FullShape(title: 'already built');

        $this->assertSame($shape, $this->resolver()->resolve($shape));
    }

    #[Test]
    public function a_shape_declining_every_capability_resolves_without_complaint(): void
    {
        $this->assertInstanceOf(BareShape::class, $this->resolver()->resolve(BareShape::class));
    }

    #[Test]
    public function shapes_are_per_call_but_the_resolver_itself_is_shared(): void
    {
        config()->set('beam.mdx.frontmatter.shape', FullShape::class);

        // Two halves of one contract, and getting either backwards is a real defect.
        //
        // SHAPES are per-call: a shared shape would hand one mutable object to every file parsed in a
        // request.
        $this->assertNotSame($this->resolver()->resolve(), $this->resolver()->resolve());

        // The RESOLVER is shared, because it owns the hydration-strategy table. Resolved per-call it
        // would silently forget every hydrateUsing() registration — a wrong answer, not an error,
        // which is this estate's most expensive defect shape.
        $this->assertSame($this->resolver(), $this->resolver());
    }

    #[Test]
    public function a_registered_hydration_strategy_survives_and_wins(): void
    {
        $this->resolver()->hydrateUsing(BareShape::class, fn () => new BareShape);

        // Resolved again from the container — if the binding were not shared this would throw
        // UnhydratableFrontmatterShape, which is exactly the regression the assertion above guards.
        $parsed = (new FrontmatterParser)->parse("---\ntitle: T\n---\nbody\n");

        $this->assertInstanceOf(BareShape::class, $this->resolver()->hydrate($parsed, BareShape::class));
    }

    #[Test]
    public function a_class_that_does_not_exist_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/does not exist/');

        $this->resolver()->resolve('Splicewire\\Beam\\Mdx\\Nope');
    }

    #[Test]
    public function a_class_that_is_not_a_frontmatter_shape_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/must implement/');

        $this->resolver()->resolve(NotAShape::class);
    }

    #[Test]
    public function no_shape_configured_and_none_passed_throws_naming_the_config_key(): void
    {
        config()->set('beam.mdx.frontmatter.shape', null);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/beam\.mdx\.frontmatter\.shape/');

        $this->resolver()->resolve();
    }

    #[Test]
    public function hydrate_builds_the_shape_from_a_parsed_block(): void
    {
        $parsed = (new FrontmatterParser)->parse("---\ntitle: T\nnavOrder: 3\n---\nbody\n");

        $shape = $this->resolver()->hydrate($parsed, FullShape::class);

        $this->assertInstanceOf(FullShape::class, $shape);
        $this->assertSame('T', $shape->title);
        $this->assertSame(['nav_order' => '3'], $shape->unknown());
    }

    #[Test]
    public function hydrating_a_shape_that_declines_the_capability_throws_naming_the_fix(): void
    {
        $parsed = (new FrontmatterParser)->parse("---\ntitle: T\n---\nbody\n");

        $this->expectException(UnhydratableFrontmatterShape::class);
        $this->expectExceptionMessageMatches('/HydratesFromFrontmatter/');

        $this->resolver()->hydrate($parsed, BareShape::class);
    }

    #[Test]
    public function the_marker_is_method_free(): void
    {
        // The whole point of the marker: a shape may implement it and nothing else. If a method ever
        // lands on Frontmatter, BareShape stops compiling and this documents why that is a mistake.
        $this->assertSame([], (new \ReflectionClass(Frontmatter::class))->getMethods());
    }
}
