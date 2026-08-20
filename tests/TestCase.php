<?php

namespace Splicewire\Beam\Mdx\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Splicewire\Beam\Mdx\BeamMdxServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [BeamMdxServiceProvider::class];
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
