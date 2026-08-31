<?php

namespace Splicewire\Beam\Mdx\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Schemastud\DataSchemas\LaravelDataSchemasServiceProvider;
use Spatie\LaravelData\LaravelDataServiceProvider;
use Splicewire\Beam\Mdx\BeamMdxServiceProvider;

class TestCase extends Orchestra
{
    /**
     * ⚠️ Testbench does NOT auto-discover. A provider left off this list never boots, and the class it
     * would have bound is usually still auto-resolvable — so the container hands back a fresh,
     * unbound, default-constructed instance instead of failing. Green suite, empty object.
     *
     * Both data providers are named for that reason, not for tidiness:
     *  - `LaravelDataServiceProvider` — without it `config('data')` is `null` and every
     *    `Data::from()` fatals. {@see assertDataConfigIsLoaded}, which fails loudly if it is dropped.
     *  - `LaravelDataSchemasServiceProvider` — without it `SchemaIdResolver` stays auto-resolvable
     *    with a nullable `$baseUri`, so it declares NO authority while the config value sits right
     *    there. That one does not fail; it answers wrongly.
     */
    protected function getPackageProviders($app): array
    {
        return [
            LaravelDataServiceProvider::class,
            LaravelDataSchemasServiceProvider::class,
            BeamMdxServiceProvider::class,
        ];
    }

    /**
     * The guard on the trap above: a future edit that drops `LaravelDataServiceProvider` from the list
     * turns every `Data::from()` into a fatal whose message names neither the provider nor the list.
     * Asserting the config is loaded turns that into one legible failure.
     */
    protected function assertDataConfigIsLoaded(): void
    {
        $this->assertIsArray(
            config('data'),
            'config("data") is null — LaravelDataServiceProvider is missing from getPackageProviders().'
        );
    }

    /** A throwaway content tree with one published essay, one draft essay, one broadcast. */
    protected function seedContent(): string
    {
        $root = sys_get_temp_dir().'/beam-mdx-test-'.uniqid();
        @mkdir($root.'/essays', 0777, true);
        @mkdir($root.'/broadcasts', 0777, true);

        file_put_contents(
            $root.'/essays/published.mdx',
            "---\ntitle: Published\ndatePublished: '2026-01-01'\n---\n\nbody\n",
        );
        file_put_contents(
            $root.'/essays/draft-no-date.mdx',
            "---\ntitle: Draft\n---\n\nbody\n",
        );
        file_put_contents(
            $root.'/broadcasts/launch.mdx',
            "---\ntitle: Launch\n---\n\nbody\n",
        );
        file_put_contents(
            $root.'/about.mdx',
            "---\ntitle: About\n---\n\nbody\n",
        );

        // The gated (access:) axis: one public doc, one root-gated, one any-of list.
        @mkdir($root.'/docs', 0777, true);
        file_put_contents(
            $root.'/docs/open.mdx',
            "---\ntitle: Open\n---\n\nbody\n",
        );
        file_put_contents(
            $root.'/docs/guarded.mdx',
            "---\ntitle: Guarded\naccess: root\n---\n\nbody\n",
        );
        file_put_contents(
            $root.'/docs/guarded-list.mdx',
            "---\ntitle: List\naccess: [support.view, billing.view]\n---\n\nbody\n",
        );

        config(['beam.mdx.content_path' => $root]);

        return $root;
    }
}
