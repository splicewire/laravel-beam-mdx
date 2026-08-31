<?php

namespace Splicewire\Beam\Mdx\Tests\Frontmatter\Fixtures;

use Splicewire\Beam\Mdx\Frontmatter\Contracts\Frontmatter;

/**
 * A shape declining EVERY capability — the minimal legal case. Per the backing precedent, declining
 * is an answer, not an error: this must resolve without complaint and only fail if someone asks it
 * to hydrate.
 */
class BareShape implements Frontmatter {}
