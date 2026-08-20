<?php

namespace Splicewire\Beam\Mdx\Docs\Regenerate;

/*
 * ── Seam note (beam-docs-satellite ticket 06 / ADR-0210 §7) ──────────────────────────────────────
 *
 * This whole `Docs/Regenerate/` tree is DOCS knowledge inside a package named for a FORMAT, which is
 * the seam violation the original ux↔mdx steer was trying to avoid. ADR-0210 §7 says it is audited and
 * moved or deleted during extraction.
 *
 * The audit ran and it is NOT dead: `splicewire/tower` (three Satellite guides) and `splicewire-app`
 * (its own guide classes plus a PmSchemaCompiler binding) both consume it today. So deleting it is off
 * the table, and moving it is a cross-repo relocation touching two consumers outside the beam packages
 * this ticket ships — which makes it its own unit of work, not a rider. It stays here, named, until
 * that lands.
 *
 * After ADR-0209 retired `beamMdxShow`/`beamMdxPage`, what remains of "beam-mdx means the format and
 * nothing else" is this tree and the content-plane doctor — and the doctor's draft/gated bundle checks
 * only become dead machinery once hosts CONVERT their tracks to entries (ticket 18), so retiring it now
 * would remove a live guard.
 * ────────────────────────────────────────────────────────────────────────────────────────────────
 */

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
