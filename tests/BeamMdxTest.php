<?php

namespace Splicewire\Beam\Mdx\Tests;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use PHPUnit\Framework\Attributes\Test;
use Splicewire\Beam\Mdx\Mdx;

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
    public function it_treats_access_gated_content_as_non_public(): void
    {
        $this->seedContent();
        config(['app.env' => 'production', 'beam-mdx.preview_envs' => []]);

        // Ungated docs are public and read back no gate.
        $this->assertFalse(Mdx::isGated('docs/open'));
        $this->assertNull(Mdx::gate('docs/open'));
        $this->assertTrue(Mdx::isVisible('docs/open'));

        // A gated file is non-public, and its opaque tokens read back verbatim.
        $this->assertTrue(Mdx::isGated('docs/guarded'));
        $this->assertSame(['root'], Mdx::gate('docs/guarded'));
        $this->assertSame(['support.view', 'billing.view'], Mdx::gate('docs/guarded-list'));
        $this->assertFalse(Mdx::isVisible('docs/guarded'), 'a gated file never resolves on the public route');

        // Gating ignores the preview allowlist (it is a confidentiality boundary, not a draft).
        config(['app.env' => 'staging', 'beam-mdx.preview_envs' => ['staging']]);
        $this->assertFalse(Mdx::isVisible('docs/guarded'));

        $this->assertSame(['docs/guarded', 'docs/guarded-list'], Mdx::gatedNames());
    }

    #[Test]
    public function doctor_fails_when_a_gated_slug_leaks_into_the_bundle(): void
    {
        $root = $this->seedContent();
        config(['app.env' => 'production', 'beam-mdx.preview_envs' => []]);

        $assets = $root.'/build/assets';
        @mkdir($assets, 0777, true);
        file_put_contents($assets.'/app-abc123.js', 'console.log("open guide");');
        config(['beam-mdx.build_assets_path' => $assets]);

        $this->assertSame(0, Artisan::call('splicewire:beam:mdx-doctor'), 'doctor passes with no gated leak');

        // Leak a gated slug into the bundle.
        file_put_contents($assets.'/leak-ghi789.js', 'const s = "guarded";');
        $this->assertSame(1, Artisan::call('splicewire:beam:mdx-doctor'), 'doctor fails when a gated slug is in the bundle');
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

        $this->assertSame(0, Artisan::call('splicewire:beam:mdx-doctor'), 'doctor passes with no leak');

        // Now leak a draft slug into the bundle.
        file_put_contents($assets.'/leak-def456.js', 'const s = "draft-no-date";');
        $this->assertSame(1, Artisan::call('splicewire:beam:mdx-doctor'), 'doctor fails when a draft slug is in the bundle');
    }
}
