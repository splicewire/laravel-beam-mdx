<?php

namespace Splicewire\BeamMdx\Tests;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use PHPUnit\Framework\Attributes\Test;
use Splicewire\BeamMdx\Mdx;

class BeamMdxTest extends TestCase
{
    #[Test]
    public function it_classifies_drafts_by_the_locked_convention(): void
    {
        $this->seedContent();

        $this->assertFalse(Mdx::isDraft('essays/published'), 'dated essay is not a draft');
        $this->assertTrue(Mdx::isDraft('essays/draft-no-date'), 'undated essay is a draft');
        $this->assertTrue(Mdx::isDraft('broadcasts/launch'), 'a broadcast is structurally always draft');
        $this->assertFalse(Mdx::isDraft('about'), 'a root page is never a draft');

        $this->assertSame(
            ['broadcasts/launch', 'essays/draft-no-date'],
            Mdx::draftNames(),
        );
    }

    #[Test]
    public function visibility_follows_the_preview_allowlist(): void
    {
        $this->seedContent();

        config(['app.env' => 'production', 'beam-mdx.preview_envs' => []]);
        $this->assertTrue(Mdx::isVisible('essays/published'));
        $this->assertFalse(Mdx::isVisible('essays/draft-no-date'), 'draft hidden outside allowlist');
        $this->assertFalse(Mdx::isVisible('broadcasts/launch'));

        config(['app.env' => 'staging', 'beam-mdx.preview_envs' => ['local', 'staging']]);
        $this->assertTrue(Mdx::isVisible('essays/draft-no-date'), 'draft visible when env allowlisted');
        $this->assertTrue(Mdx::isVisible('broadcasts/launch'));
    }

    #[Test]
    public function the_show_macro_404s_a_draft_but_serves_a_published_slug(): void
    {
        $this->seedContent();
        config(['app.env' => 'production', 'beam-mdx.preview_envs' => []]);

        Route::beamMdxShow('essays', 'content/show')->name('essays.show');

        // X-Inertia makes Inertia return its JSON payload (no root blade view needed).
        $inertia = ['X-Inertia' => 'true'];
        $this->get('/essays/published', $inertia)->assertOk();
        $this->get('/essays/draft-no-date', $inertia)->assertNotFound();
        $this->get('/essays/does-not-exist', $inertia)->assertNotFound();
    }

    #[Test]
    public function the_preview_middleware_gates_a_whole_surface(): void
    {
        $this->seedContent();

        Route::middleware('beam-mdx.preview')->get('/broadcasts', fn () => Inertia::render('broadcasts/index'));

        config(['app.env' => 'production', 'beam-mdx.preview_envs' => []]);
        $this->get('/broadcasts', ['X-Inertia' => 'true'])->assertNotFound();

        config(['app.env' => 'local', 'beam-mdx.preview_envs' => ['local']]);
        $this->get('/broadcasts', ['X-Inertia' => 'true'])->assertOk();
    }

    #[Test]
    public function doctor_passes_when_no_draft_leaks_and_fails_when_one_does(): void
    {
        $root = $this->seedContent();
        config([
            'app.env' => 'production',
            'beam-mdx.preview_envs' => [],
        ]);

        // A clean bundle dir — no draft slug present.
        $assets = $root.'/build/assets';
        @mkdir($assets, 0777, true);
        file_put_contents($assets.'/app-abc123.js', 'console.log("published essay");');
        config(['beam-mdx.build_assets_path' => $assets]);

        $this->assertSame(0, Artisan::call('beam-mdx:doctor'), 'doctor passes with no leak');

        // Now leak a draft slug into the bundle.
        file_put_contents($assets.'/leak-def456.js', 'const s = "draft-no-date";');
        $this->assertSame(1, Artisan::call('beam-mdx:doctor'), 'doctor fails when a draft slug is in the bundle');
    }
}
