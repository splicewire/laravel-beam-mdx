<?php

namespace Splicewire\Beam\Mdx\Tests\Routing;

use PHPUnit\Framework\Attributes\Test;
use Splicewire\Beam\Mdx\Routing\ContentRoutes;
use Splicewire\Beam\Mdx\Routing\MissingInertia;
use Splicewire\Beam\Mdx\Tests\TestCase;

/**
 * Inertia is a SUGGESTED dependency, not a required one — it is reached from one file, for the two
 * `Inertia::render()` calls inside opt-in route macros. Requiring it forced a frontend adapter onto
 * every consumer, including packages with no frontend at all, which is what kept
 * `splicewire/laravel-beam-mcp` from sharing this package's frontmatter grammar.
 *
 * ⚠️ **The negative path is not directly testable here.** Inertia is in `require-dev`, so
 * `class_exists()` is true for the whole suite; simulating its absence would need a separate harness
 * with a different autoloader. What IS asserted: the guard exists, is public and static (the binding
 * detail below), and its message names the package and the macro.
 */
class SuggestedInertiaTest extends TestCase
{
    #[Test]
    public function the_guard_passes_when_inertia_is_installed(): void
    {
        ContentRoutes::assertInertiaInstalled('beamMdxShow');

        $this->assertTrue(class_exists(\Inertia\Inertia::class));
    }

    #[Test]
    public function the_guard_is_public_and_static_because_laravel_rebinds_macro_closures(): void
    {
        // Laravel binds a macro closure to the Router INSTANCE, which rebinds `self` too — so
        // `self::assertInertiaInstalled()` inside the closure resolves against Router and dies as
        // "Attribute [assertInertiaInstalled] does not exist". It cost a red suite to find; this
        // asserts the shape that fixes it, so a later "tidy it to private" fails here instead.
        $method = new \ReflectionMethod(ContentRoutes::class, 'assertInertiaInstalled');

        $this->assertTrue($method->isPublic());
        $this->assertTrue($method->isStatic());
    }

    #[Test]
    public function the_failure_names_the_package_and_the_macro(): void
    {
        $message = MissingInertia::forMacro('beamMdxPage')->getMessage();

        $this->assertStringContainsString('inertiajs/inertia-laravel', $message);
        $this->assertStringContainsString('beamMdxPage', $message);
        $this->assertStringContainsString('suggested', $message);
    }
}
