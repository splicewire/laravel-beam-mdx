<?php

namespace Splicewire\Beam\Mdx\Docs\Regenerate;

/**
 * The single JSON-encoding seam for regenerated guide artifacts. Every artifact is written
 * with the same flags the guides commit today — pretty-printed, slashes and unicode
 * unescaped — so switching from the `regenerate*.sh` + `jq` mechanism to native PHP produces
 * no spurious encoding diff. This replaces the `jq` post-processing the scripts delegated to.
 */
class DocsJson
{
    public const FLAGS = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    public static function encode(mixed $value): string
    {
        return json_encode($value, self::FLAGS);
    }
}
