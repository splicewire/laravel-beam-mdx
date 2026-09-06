<?php

namespace Splicewire\Beam\Mdx\Tests\Doctor;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Rushing\Doctor\DoctorStatus;
use Splicewire\Beam\Mdx\Doctor\MdxContentPlaneAudit;
use Splicewire\Beam\Mdx\Tests\TestCase;

class MdxContentPlaneAuditTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().'/beam-mdx-applicability-'.bin2hex(random_bytes(12));
        mkdir($this->root.'/assets', 0777, true);
        file_put_contents($this->root.'/assets/app.js', 'const publicPage = true;');
        $this->app->setBasePath($this->root);

        config([
            'app.env' => 'production',
            'beam.mdx.preview_envs' => [],
            'beam.mdx.content_path' => resource_path('js/content'),
            'beam.mdx.build_assets_path' => $this->root.'/assets',
        ]);
    }

    protected function tearDown(): void
    {
        try {
            (new Filesystem)->deleteDirectory($this->root);
        } finally {
            parent::tearDown();
        }
    }

    public static function fileMounts(): array
    {
        return [
            'show, missing tree' => ['show', false],
            'show, empty tree' => ['show', true],
            'page, missing tree' => ['page', false],
            'page, empty tree' => ['page', true],
        ];
    }

    #[Test]
    #[DataProvider('fileMounts')]
    public function a_file_mount_still_requires_content_when_entries_are_also_mounted(string $macro, bool $directoryExists): void
    {
        if ($directoryExists) {
            mkdir(resource_path('js/content'), 0777, true);
        }

        Route::prefix('journal')->name('host.')->group(function () use ($macro): void {
            $route = $macro === 'show'
                ? Route::beamMdxShow('essays', 'content/show')
                : Route::beamMdxPage('about', 'content/show', 'about');

            $route->name('custom-content');
        });
        $this->mountEntryRoute();

        $finding = (new MdxContentPlaneAudit)->run()[0];

        $this->assertSame(DoctorStatus::Fail, $finding->status);
        $this->assertTrue($finding->conclusive);
        $this->assertSame('MDX plane not wired', $finding->check);
        $this->assertStringContainsString(resource_path('js/content'), $finding->detail);
    }

    #[Test]
    public function a_custom_content_root_cannot_be_skipped_just_because_it_is_missing(): void
    {
        config(['beam.mdx.content_path' => $this->root.'/custom-content']);
        $this->mountEntryRoute();

        $finding = (new MdxContentPlaneAudit)->run()[0];

        $this->assertSame(DoctorStatus::Fail, $finding->status);
        $this->assertSame('MDX plane not wired', $finding->check);
        $this->assertStringContainsString($this->root.'/custom-content', $finding->detail);
    }

    #[Test]
    public function files_are_audited_without_a_file_route_even_on_an_entry_host(): void
    {
        $this->mountEntryRoute();
        mkdir(resource_path('js/content'), 0777, true);
        file_put_contents(resource_path('js/content/private-draft.mdx'), "---\ndraft: true\n---\nBody\n");

        $audit = new MdxContentPlaneAudit;
        $clean = $audit->run();
        $this->assertSame(DoctorStatus::Pass, $clean[0]->status);
        $this->assertSame([], array_values(array_filter($clean, fn ($finding) => $finding->status === DoctorStatus::Fail)));

        file_put_contents($this->root.'/assets/app.js', 'const slug = "private-draft";');
        $leaks = array_values(array_filter($audit->run(), fn ($finding) => $finding->status === DoctorStatus::Fail));

        $this->assertCount(1, $leaks);
        $this->assertSame('Bundle', $leaks[0]->check);
        $this->assertStringContainsString('private-draft', $leaks[0]->detail);
    }

    #[Test]
    public function entry_routes_and_preview_mode_do_not_disable_gated_bundle_checks(): void
    {
        $this->mountEntryRoute();
        config(['app.env' => 'staging', 'beam.mdx.preview_envs' => ['staging']]);
        mkdir(resource_path('js/content'), 0777, true);
        file_put_contents(resource_path('js/content/secret-guide.mdx'), "---\naccess: root\n---\nBody\n");
        file_put_contents($this->root.'/assets/app.js', 'const slug = "secret-guide";');

        $leaks = array_values(array_filter((new MdxContentPlaneAudit)->run(), fn ($finding) => $finding->status === DoctorStatus::Fail));

        $this->assertCount(1, $leaks);
        $this->assertSame('Bundle', $leaks[0]->check);
        $this->assertStringContainsString('gated slug(s)', $leaks[0]->detail);
        $this->assertStringContainsString('secret-guide', $leaks[0]->detail);
    }

    private function mountEntryRoute(): void
    {
        // ADR-0209's runtime route shape, without installing beam-ux or dispatching a DB-backed
        // request. An entry mount is positive evidence of entries, never proof of entries ONLY.
        Route::get('{path}', ['Splicewire\\Beam\\Ux\\Http\\Controllers\\PublicEntryController', '__invoke'])
            ->where('path', '.*')
            ->defaults('beamUxRealm', 'site')
            ->defaults('beamUxPage', 'site/entry')
            ->name('beam.ux.site.show');
    }
}
