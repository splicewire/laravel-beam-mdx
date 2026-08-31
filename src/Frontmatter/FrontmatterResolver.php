<?php

namespace Splicewire\Beam\Mdx\Frontmatter;

use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use Splicewire\Beam\Mdx\Frontmatter\Contracts\Frontmatter;
use Splicewire\Beam\Mdx\Frontmatter\Contracts\HydratesFromFrontmatter;

/**
 * Resolves WHICH declared shape reads a `---` block (frontmatter-declaration-seam ticket 02) — the
 * override seam. Overriding is the expected path, not an escape hatch: the shipped default exists so a
 * consumer with no opinion does not have to have one.
 *
 * ## The three forms, and one deliberate deviation from the backing precedent
 *
 * `BackingResolver` takes an instance, a class-string, or a model class-string. Two of those carry over
 * and the third does not, because a backing is a **service** (it queries) while a frontmatter shape is a
 * **value**:
 *
 * | given | meaning |
 * |---|---|
 * | a {@see Frontmatter} **instance** | used as-is — the caller already built it |
 * | a {@see Frontmatter} **class-string** | container-resolved **per call**, so it may take constructor injection |
 * | `null` | the configured default, `beam.mdx.frontmatter.shape` |
 *
 * There is no "any other class-string" arm. A backing could fall back to reading an unknown class-string
 * as an Eloquent model because that is a real, single, obvious meaning; a frontmatter shape has no such
 * fallback, and inventing one would let a typo resolve to something silently wrong. So an unknown or
 * non-conforming class-string **throws**.
 *
 * ## What throws, and what does not
 *
 * Per `AGENTS.md` — *a check whose answer depends on the host must not throw.*
 *
 *  - A configured class that does not exist, or does not implement {@see Frontmatter} → **throws**.
 *    That is grammar the declaration's author could have gotten right without knowing which host would
 *    load it.
 *  - A shape declining a capability → **not an error.** Absence is a return value; the caller decides.
 *  - An authored key no shape claims → **advisory**, reported by the audit, never raised here. Which
 *    keys a host's authors write is a fact about the host.
 *
 * ## Per-call, never a singleton
 *
 * `resolve()` builds a fresh shape every call and the suite asserts it. A shared instance would give one
 * mutable object to every file parsed in a request — and an unbound auto-resolvable singleton is this
 * estate's recorded defect that presents as a wrong answer rather than an error.
 */
class FrontmatterResolver
{
    /** The config key holding the default shape's class-string. Named in the error, so it is greppable. */
    public const CONFIG_KEY = 'beam.mdx.frontmatter.shape';

    /** @var array<class-string<Frontmatter>, callable(ParsedFrontmatter): Frontmatter> */
    protected array $strategies = [];

    public function __construct(protected Container $container) {}

    /**
     * Register a hydration strategy for a shape that declines {@see HydratesFromFrontmatter}.
     *
     * This is how a shape whose construction belongs to somebody else — a `spatie/laravel-data` class,
     * built by the data pipeline rather than by itself — joins the seam without the marker growing a
     * method. Ticket 03's `FrontmatterData` registers exactly one.
     *
     * @param  class-string<Frontmatter>  $shape
     * @param  callable(ParsedFrontmatter): Frontmatter  $hydrator
     */
    public function hydrateUsing(string $shape, callable $hydrator): static
    {
        $this->strategies[$shape] = $hydrator;

        return $this;
    }

    /**
     * Resolve the shape to use.
     *
     * @param  Frontmatter|class-string<Frontmatter>|null  $shape
     *
     * @throws InvalidArgumentException on a missing, unknown or non-conforming declaration
     */
    public function resolve(Frontmatter|string|null $shape = null): Frontmatter
    {
        if ($shape instanceof Frontmatter) {
            return $shape;
        }

        return $this->container->make($this->shapeClass($shape));
    }

    /**
     * Build a shape from a parsed block.
     *
     * @param  Frontmatter|class-string<Frontmatter>|null  $shape
     *
     * @throws UnhydratableFrontmatterShape when the shape neither implements the capability nor has a
     *                                      registered strategy
     */
    public function hydrate(ParsedFrontmatter $parsed, Frontmatter|string|null $shape = null): Frontmatter
    {
        $class = $shape instanceof Frontmatter ? $shape::class : $this->shapeClass($shape);

        if (isset($this->strategies[$class])) {
            return ($this->strategies[$class])($parsed);
        }

        if (is_subclass_of($class, HydratesFromFrontmatter::class)) {
            return $class::fromFrontmatter($parsed);
        }

        throw UnhydratableFrontmatterShape::for($class);
    }

    /**
     * The class-string a resolve would use, validated. Public because the audit needs to ask "which
     * shape governs here?" without building one.
     *
     * @param  class-string<Frontmatter>|null  $shape
     * @return class-string<Frontmatter>
     */
    public function shapeClass(?string $shape = null): string
    {
        $class = $shape ?? config(self::CONFIG_KEY);

        if (! is_string($class) || $class === '') {
            throw new InvalidArgumentException(
                'No frontmatter shape declared: pass one explicitly or set ['.self::CONFIG_KEY.'].'
            );
        }

        if (! class_exists($class)) {
            throw new InvalidArgumentException("Frontmatter shape [{$class}] does not exist.");
        }

        if (! is_subclass_of($class, Frontmatter::class)) {
            throw new InvalidArgumentException(
                "Frontmatter shape [{$class}] must implement ".Frontmatter::class.'.'
            );
        }

        return $class;
    }
}
