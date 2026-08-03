<?php

namespace Splicewire\Beam\Mdx\Docs\Regenerate;

/**
 * Compiles the ProseMirror schema summary (blocks artifact 13) from the base + profile manifests.
 * The compilation is inherently JavaScript — it runs blockdoc's `assemblePMSchema` — so this seam
 * lets the blocks guide obtain the summary without embedding node orchestration inline, and lets
 * tests substitute a canned summary (no node/vitest, no external tool).
 */
interface PmSchemaCompiler
{
    /**
     * Compile the PM-schema summary and return its JSON body verbatim (the JS-native encoding
     * the artifact commits), given the base and profile manifest JSON the summary is assembled from.
     */
    public function compile(string $baseManifestJson, string $profileManifestJson): string;
}
