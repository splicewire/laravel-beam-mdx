<?php

namespace Splicewire\Beam\Mdx\Docs\Regenerate;

use Closure;

/**
 * A single host precondition the `docs:regenerate` family asserts (never provisions).
 * `--check` evaluates each and, on failure, reports the specific, actionable message so
 * the maintainer fixes the setup instead of debugging a half-written run.
 */
class Precondition
{
    /**
     * @param  Closure():bool  $check  returns true when the precondition is satisfied
     */
    public function __construct(
        public string $label,
        private Closure $check,
        public string $failureMessage,
    ) {}

    public function satisfied(): bool
    {
        return (bool) ($this->check)();
    }
}
