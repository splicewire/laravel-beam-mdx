<?php

namespace Splicewire\Beam\Mdx\Tests\Lens;

use Illuminate\Validation\ValidationException;
use Splicewire\Beam\Mdx\Lens\MdxLens;
use Splicewire\Beam\Mdx\Lens\NodeMdxLens;
use Splicewire\Beam\Mdx\Tests\TestCase;

/**
 * ux-0233 d2 — the MDX Lens through its Node worker. The worker-contract tests need only `node`. The round trip runs
 * the real `@splicewire/beam-mdx/lens` from a host whose node_modules hold it built: set `BEAM_MDX_LENS_HOST` to that
 * host's root (a beam JS checkout with `beam-mdx` and `beam-ux` built serves).
 */
final class NodeMdxLensTest extends TestCase
{
    public function test_the_container_binds_the_node_lens(): void
    {
        $this->assertInstanceOf(NodeMdxLens::class, $this->app->make(MdxLens::class));
    }

    public function test_a_host_without_the_lens_package_is_told_to_install_it(): void
    {
        $this->requireNode();
        $host = sys_get_temp_dir().'/beam-mdx-lens-empty-host-'.bin2hex(random_bytes(4));
        mkdir($host);

        try {
            (new NodeMdxLens(workingDirectory: $host))->read('# Hi');
            $this->fail('A host without @splicewire/beam-mdx must refuse.');
        } catch (ValidationException $e) {
            $message = $e->errors()['source'][0];
            $this->assertStringContainsString('cannot resolve [@splicewire/beam-mdx/lens]', $message);
            $this->assertStringContainsString('npm i @splicewire/beam-mdx/lens', $message);
        } finally {
            rmdir($host);
        }
    }

    public function test_a_missing_worker_script_refuses_on_source(): void
    {
        $this->expectException(ValidationException::class);

        (new NodeMdxLens(script: '/nonexistent/mdx-lens.mjs'))->read('# Hi');
    }

    public function test_the_real_lens_reads_prints_and_settles_through_the_worker(): void
    {
        $lens = $this->realLens();

        $read = $lens->read("# Title\n\nOne line\nand the next.\n\n<Callout type=\"tip\">\n\nKeep it **short**.\n\n</Callout>\n");

        $this->assertNull($read['meta']);
        $this->assertSame(['h1', 'p', 'Callout'], array_column($read['doc'], 'name'));
        $this->assertSame([['kind' => 'text', 'value' => 'One line and the next.']], $read['doc'][1]['children']);
        $this->assertSame([['name' => 'type', 'kind' => 'string', 'value' => 'tip']], $read['doc'][2]['props']);

        $printed = $lens->print($read['doc']);
        $this->assertSame("# Title\n\nOne line and the next.\n\n<Callout type=\"tip\">\n\nKeep it **short**.\n\n</Callout>\n", $printed);
        $this->assertSame($printed, $lens->print($lens->read($printed)['doc']), 'printing reaches a fixed point');
    }

    public function test_the_real_lens_refuses_code_outside_the_jsx_on_source_and_returns_meta(): void
    {
        $lens = $this->realLens();

        $this->assertSame("export const meta = {title: 'Hi'}", $lens->read("export const meta = {title: 'Hi'}\n\n# Hi\n")['meta']);

        try {
            $lens->read("import Chart from './chart'\n\n# Hi\n");
            $this->fail('An import is code outside the JSX.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('holds no code outside its JSX', $e->errors()['source'][0]);
        }
    }

    private function realLens(): NodeMdxLens
    {
        $this->requireNode();
        $host = getenv('BEAM_MDX_LENS_HOST');
        if (! is_string($host) || ! is_file($host.'/node_modules/@splicewire/beam-mdx/package.json')) {
            $this->markTestSkipped('Set BEAM_MDX_LENS_HOST to a host root whose node_modules hold @splicewire/beam-mdx built.');
        }

        return new NodeMdxLens(workingDirectory: $host);
    }

    private function requireNode(): void
    {
        exec('command -v node', $output, $status);
        if ($status !== 0) {
            $this->markTestSkipped('node is not on PATH.');
        }
    }
}
