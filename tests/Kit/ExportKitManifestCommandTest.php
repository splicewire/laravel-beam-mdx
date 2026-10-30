<?php

namespace Splicewire\Beam\Mdx\Tests\Kit;

use Splicewire\Beam\Mdx\Kit\KitManifestExporter;
use Splicewire\Beam\Mdx\Tests\TestCase;

final class ExportKitManifestCommandTest extends TestCase
{
    public function test_the_exporter_writes_the_registered_php_kit_manifest_as_json(): void
    {
        $path = sys_get_temp_dir().'/beam-mdx-kit-manifest-'.bin2hex(random_bytes(8)).'.json';

        try {
            $this->artisan('splicewire:beam:mdx:export-kit', ['--write' => true, '--output' => $path])->assertExitCode(0);

            $actual = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame((new KitManifestExporter)->manifest(), $actual);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function test_export_refuses_to_write_without_the_explicit_write_option(): void
    {
        $path = sys_get_temp_dir().'/beam-mdx-kit-manifest-'.bin2hex(random_bytes(8)).'.json';
        file_put_contents($path, 'preserve existing artifact');

        try {
            $this->artisan('splicewire:beam:mdx:export-kit', ['--output' => $path])->assertExitCode(1);
            $this->assertSame('preserve existing artifact', file_get_contents($path));
        } finally {
            unlink($path);
        }
    }
}
