<?php

namespace Splicewire\Beam\Mdx\Docs\Regenerate;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * The production {@see PmSchemaCompiler}: runs blockdoc's `assemblePMSchema` through a scratch
 * vitest in the blockdoc working copy, exactly as `regenerate-blocks.sh` did — but the manifests
 * and output cross the boundary as real files, not a bash-interpolated heredoc. The blockdoc path
 * and node availability are asserted by the guide's preflight before this runs.
 */
class NodeVitestPmSchemaCompiler implements PmSchemaCompiler
{
    public function compile(string $baseManifestJson, string $profileManifestJson): string
    {
        $blockdoc = rtrim((string) config('docs-regenerate.blockdoc_path'), '/');

        if (! is_dir($blockdoc)) {
            throw new RuntimeException("blockdoc working copy not found at {$blockdoc}");
        }

        $scratch = $blockdoc.'/tests/__scratch-docs-manifest-dump.test.ts';
        $basePath = tempnam(sys_get_temp_dir(), 'pm-base-').'.json';
        $profilePath = tempnam(sys_get_temp_dir(), 'pm-profile-').'.json';
        $outPath = tempnam(sys_get_temp_dir(), 'pm-out-').'.json';

        file_put_contents($basePath, $baseManifestJson);
        file_put_contents($profilePath, $profileManifestJson);

        file_put_contents($scratch, $this->scratchTest($basePath, $profilePath, $outPath));

        try {
            $process = Process::fromShellCommandline(
                'npx vitest run tests/__scratch-docs-manifest-dump.test.ts',
                $blockdoc,
                timeout: 180,
            );
            $process->run();

            if (! $process->isSuccessful() || ! is_file($outPath)) {
                throw new RuntimeException('PM-schema compile failed: '.$process->getErrorOutput());
            }

            return (string) file_get_contents($outPath);
        } finally {
            @unlink($scratch);
            @unlink($basePath);
            @unlink($profilePath);
            @unlink($outPath);
        }
    }

    private function scratchTest(string $basePath, string $profilePath, string $outPath): string
    {
        return <<<TS
        import { readFile, writeFile } from 'node:fs/promises';
        import { it } from 'vitest';
        import { assemblePMSchema } from '../src/core/index';

        it('dumps the compiled PM schema for the docs guide', async () => {
          const base = JSON.parse(await readFile('{$basePath}', 'utf8'));
          const profile = JSON.parse(await readFile('{$profilePath}', 'utf8')).data;
          const schema = assemblePMSchema([base, profile]);
          const summary = {
            docContent: schema.nodes.doc.spec.content,
            nodes: Object.fromEntries(
              Object.entries(schema.nodes)
                .filter(([name]) => !['doc', 'text'].includes(name))
                .map(([name, type]) => [name, { group: type.spec.group ?? null, content: type.spec.content ?? null, attrs: Object.keys(type.spec.attrs ?? {}) }]),
            ),
            marks: Object.keys(schema.marks),
          };
          await writeFile('{$outPath}', JSON.stringify(summary, null, 2));
        });
        TS;
    }
}
