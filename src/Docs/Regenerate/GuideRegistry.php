<?php

namespace Splicewire\Beam\Mdx\Docs\Regenerate;

use InvalidArgumentException;

/**
 * The registry of regenerable guides. Resolves the definition classes listed in
 * `config('docs-regenerate.guides')` through the container and records, per guide, which repo
 * owns its command. The parent `docs:regenerate` command reads this for `--list`, `--check`,
 * and dispatch.
 */
class GuideRegistry
{
    /** @var array<string, GuideDefinition> keyed by guide name, registration order preserved */
    private array $guides = [];

    /**
     * @param  list<GuideDefinition>  $guides
     */
    public function __construct(array $guides)
    {
        foreach ($guides as $guide) {
            $this->guides[$guide->name()] = $guide;
        }
    }

    public static function fromConfig(): self
    {
        $guides = array_map(
            static fn (string $class): GuideDefinition => app($class),
            (array) config('docs-regenerate.guides', []),
        );

        return new self($guides);
    }

    /** @return list<GuideDefinition> */
    public function all(): array
    {
        return array_values($this->guides);
    }

    public function has(string $name): bool
    {
        return isset($this->guides[$name]);
    }

    public function get(string $name): GuideDefinition
    {
        return $this->guides[$name]
            ?? throw new InvalidArgumentException("Unknown guide: {$name}");
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->guides);
    }
}
