<?php

namespace Splicewire\Beam\Mdx;

use Illuminate\Routing\Router;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Splicewire\Beam\Mdx\Console\BeamMdxDoctorCommand;
use Splicewire\Beam\Mdx\Doctor\MdxContentPlaneAudit;
use Splicewire\Beam\Mdx\Frontmatter\FrontmatterResolver;
use Splicewire\Beam\Mdx\Http\Middleware\EnsurePreviewAllowed;
use Splicewire\Beam\Mdx\Routing\ContentRoutes;

class BeamMdxServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-beam-mdx')
            ->hasConfigFile('beam/mdx')
            ->hasCommand(BeamMdxDoctorCommand::class);
    }

    public function packageRegistered(): void
    {
        // SHARED on purpose: the resolver owns the hydration-strategy table, so a per-call binding
        // would silently forget every hydrateUsing() registration. The shapes it *produces* are
        // per-call — see FrontmatterResolver's own docblock for the two halves of that contract.
        $this->app->singleton(FrontmatterResolver::class);
    }

    public function packageBooted(): void
    {
        // The catch-all content/essay route macros (Route::beamMdxShow / beamMdxPage).
        ContentRoutes::registerMacros();

        // The `beam-mdx.preview` alias — gate a whole surface to preview-allowlisted envs.
        $this->app->make(Router::class)->aliasMiddleware('beam-mdx.preview', EnsurePreviewAllowed::class);

        // Register the content-plane audit — ADVISORY — DOWN into beam-core's doctor aggregation
        // manifest, so one `splicewire:beam:doctor` run reports it with the rest of the family.
        // Guarded by string class-name so this package keeps booting in a host without beam-core
        // installed (this package does not require beam; the manifest binding simply won't exist).
        if ($this->app->bound('Splicewire\\Beam\\Doctor\\BeamDoctorManifest')) {
            $this->app->make('Splicewire\\Beam\\Doctor\\BeamDoctorManifest')->register(
                package: 'splicewire/laravel-beam-mdx',
                audit: MdxContentPlaneAudit::class,
                gate: false,
            );
        }
    }
}
