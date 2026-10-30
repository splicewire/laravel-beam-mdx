<?php

namespace Splicewire\Beam\Mdx\Tests\Kit;

use Splicewire\Beam\Mdx\Kit\KitManifestExporter;
use Splicewire\Beam\Mdx\Tests\TestCase;

final class ExportKitManifestCommandTest extends TestCase
{
    public function test_the_exporter_writes_the_registered_php_kit_manifest_as_json(): void
    {
        $configuredOutput = getenv('BEAM_MDX_KIT_MANIFEST_OUTPUT');
        $path = $configuredOutput !== false && $configuredOutput !== ''
            ? $configuredOutput
            : sys_get_temp_dir().'/beam-mdx-kit-manifest-'.uniqid().'.json';
        $removeAfterTest = $path !== $configuredOutput;

        try {
            $this->artisan('splicewire:beam:mdx:export-kit', ['--output' => $path])->assertExitCode(0);

            $actual = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame((new KitManifestExporter)->manifest(), $actual);
        } finally {
            if ($removeAfterTest && is_file($path)) {
                unlink($path);
            }
        }
    }
}
