<?php

namespace Splicewire\Beam\Mdx\Docs\Regenerate;

use Illuminate\Console\Command;

/**
 * A registered regeneration unit. Carries the metadata the parent `docs:regenerate` command
 * needs for discovery (`--list`) and preflight (`--check`), plus the in-process derive →
 * capture → write for app-owned guides. Satellite-owned guides implement everything but
 * `regenerate()`, which throws {@see RunsInSatelliteException} because their generator objects
 * live in another repo.
 */
interface GuideDefinition
{
    /** The guide's regeneration name, e.g. `profiles` (the `{guide}` argument). */
    public function name(): string;

    /** Which repo owns this guide's command: `app` (regenerates here) or `thingsontv`. */
    public function owner(): string;

    /** True when a run spends money / calls a live LLM — the parent confirms before it. */
    public function paid(): bool;

    /** Human-readable estimated spend for a paid run, e.g. `~$0.25`, or null when free. */
    public function cost(): ?string;

    /** One-line description shown in `--list`. */
    public function summary(): string;

    /**
     * Host preconditions asserted (never provisioned) by `--check` before a run.
     *
     * @return list<Precondition>
     */
    public function preconditions(): array;

    /**
     * Derive → capture → write this guide's artifacts in-process.
     *
     * @throws RunsInSatelliteException when the guide is satellite-owned
     */
    public function regenerate(Command $command): void;
}
