<?php

namespace Splicewire\Beam\Mdx\Docs\Regenerate;

use RuntimeException;

/**
 * Thrown when a satellite-owned guide is dispatched from this app. Its derivation can
 * only run in-process in the repo that owns its generator objects (thingsontv); this app
 * hosts only the registry, discovery, and preflight surface for it.
 */
class RunsInSatelliteException extends RuntimeException
{
    public function __construct(
        public string $guide,
        public string $satelliteCommand,
    ) {
        parent::__construct(
            "The `{$guide}` guide is owned by the thingsontv satellite and regenerates there. "
            ."Run `{$satelliteCommand}` inside the satellite repo — this app only lists and preflights it."
        );
    }
}
