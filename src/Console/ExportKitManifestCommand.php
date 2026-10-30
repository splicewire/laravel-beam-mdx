<?php

namespace Splicewire\Beam\Mdx\Console;

use Illuminate\Console\Command;
use Splicewire\Beam\Mdx\Kit\KitManifestExporter;

/** Export the canonical MDX kit manifest from the server-declared Code Components. */
final class ExportKitManifestCommand extends Command
{
    protected $signature = 'splicewire:beam:mdx:export-kit {--output= : Destination JSON file}';

    protected $description = 'Export the MDX kit manifest from its PHP Blockdoc declarations.';

    public function handle(KitManifestExporter $exporter): int
    {
        $output = (string) $this->option('output');

        if ($output === '') {
            $this->components->error('Pass --output=<path> for the generated manifest.');

            return self::FAILURE;
        }

        $directory = dirname($output);
        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            $this->components->error("Could not create manifest directory: {$directory}");

            return self::FAILURE;
        }

        $json = json_encode($exporter->manifest(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";

        if (file_put_contents($output, $json) === false) {
            $this->components->error("Could not write manifest: {$output}");

            return self::FAILURE;
        }

        $this->components->info("Exported MDX kit manifest to {$output}");

        return self::SUCCESS;
    }
}
