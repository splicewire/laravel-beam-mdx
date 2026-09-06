<?php

namespace Splicewire\Beam\Mdx\Tests\Doctor;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Rushing\Doctor\DoctorStatus;
use Splicewire\Beam\Mdx\Doctor\MdxContentPlaneAudit;
use Splicewire\Beam\Mdx\Mdx;
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

    #[Test]
    public function file_content_is_expected_by_default(): void
    {
        $this->assertTrue(config('beam.mdx.file_content_expected'));
        $this->assertSame(DoctorStatus::Fail, (new MdxContentPlaneAudit)->run()[0]->status);
    }

    public static function emptyPopulations(): array
    {
        return [
            'missing directory' => ['missing'],
            'empty directory' => ['empty'],
            'other file extensions only' => ['other'],
        ];
    }

    #[Test]
    #[DataProvider('emptyPopulations')]
    public function explicitly_unexpected_empty_content_is_inconclusive(string $population): void
    {
        config(['beam.mdx.file_content_expected' => false]);
        if ($population !== 'missing') {
            mkdir(resource_path('js/content'), 0777, true);
        }
        if ($population === 'other') {
            file_put_contents(resource_path('js/content/page.tsx'), 'export default null;');
        }

        $findings = (new MdxContentPlaneAudit)->run();

        $this->assertCount(1, $findings, 'An empty optional plane must not emit ordinary clean gate/bundle findings.');
        $this->assertSame(DoctorStatus::Pass, $findings[0]->status);
        $this->assertFalse($findings[0]->conclusive);
        $this->assertSame('MDX plane not applicable', $findings[0]->check);
        $this->assertStringContainsString('beam.mdx.file_content_expected=false', $findings[0]->detail);
        $this->assertStringContainsString(resource_path('js/content'), $findings[0]->detail);
    }

    public static function protectedFiles(): array
    {
        return [
            'draft in production' => ['draft: true', 'production', 'draft slug(s)'],
            'gated in production' => ['access: root', 'production', 'gated slug(s)'],
            'gated in preview' => ['access: root', 'staging', 'gated slug(s)'],
        ];
    }

    #[Test]
    #[DataProvider('protectedFiles')]
    public function actual_files_keep_all_guards_even_when_unexpected(string $frontmatter, string $env, string $leakDetail): void
    {
        config([
            'beam.mdx.file_content_expected' => false,
            'app.env' => $env,
            'beam.mdx.preview_envs' => ['staging'],
        ]);
        $audit = new MdxContentPlaneAudit;
        $this->assertFalse($audit->run()[0]->conclusive);

        // The same audit must see a file that appears later, including in a nested directory.
        mkdir(resource_path('js/content/nested'), 0777, true);
        file_put_contents(resource_path('js/content/nested/private-guide.mdx'), "---\n{$frontmatter}\n---\nBody\n");
        $this->assertFalse(Mdx::isVisible('nested/private-guide'));

        $clean = $audit->run();
        $this->assertSame('MDX plane wired', $clean[0]->check);
        $this->assertTrue($clean[0]->conclusive);
        $this->assertSame([], array_values(array_filter($clean, fn ($finding) => $finding->status === DoctorStatus::Fail)));

        file_put_contents($this->root.'/assets/app.js', 'const slug = "private-guide";');
        $unexpected = $audit->run();
        $leaks = array_values(array_filter($unexpected, fn ($finding) => $finding->status === DoctorStatus::Fail));
        $this->assertCount(1, $leaks);
        $this->assertSame('Bundle', $leaks[0]->check);
        $this->assertStringContainsString($leakDetail, $leaks[0]->detail);

        config(['beam.mdx.file_content_expected' => true]);
        $this->assertEquals($audit->run(), $unexpected, 'Actual files receive exactly the same guards under either expectation.');
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
