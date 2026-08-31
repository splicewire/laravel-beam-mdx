<?php

namespace Splicewire\Beam\Mdx\Frontmatter\Contracts;

/**
 * The marker: "I am a declared frontmatter shape."
 *
 * **No methods, deliberately** — mirroring `Splicewire\Beam\Particle\Backing\ResourceBacking`, which is
 * this estate's existing answer to a polymorphic slot whose implementations have genuinely different
 * capabilities. Every real job here is an optional capability sub-interface
 * ({@see DeclaresFrontmatterFields}, {@see RetainsUnknownFields}, {@see HydratesFromFrontmatter}), and a
 * shape implements the ones it honestly has.
 *
 * **Declining a capability is an ANSWER, never an error.** A shape that only wants to be recognised —
 * and hydrated by a strategy someone else supplies — implements this and nothing else. Putting a method
 * here would make that impossible and force every shape to invent an answer it may not have, which is
 * the exact mistake the backing port avoided.
 *
 * The *shape* is per-consumer on purpose: what fields a `---` block carries is a fact about the
 * consumer, not about the format. Only the grammar ({@see \Splicewire\Beam\Mdx\Frontmatter\FrontmatterParser})
 * is shared.
 */
interface Frontmatter {}
