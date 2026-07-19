<?php

namespace Splicewire\BeamMdx\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Splicewire\BeamMdx\BeamMdxServiceProvider;

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

        config(['beam-mdx.content_path' => $root]);

        return $root;
    }
}
